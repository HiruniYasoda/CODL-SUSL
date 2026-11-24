<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\ActivityLog;

class DatabaseTableController extends Controller
{
    /**
     * Strict allowlist of operational tables permitted for administrative browsing.
     * Core authentication, security tokens, system settings, and audit logs are strictly protected.
     */
    public const ALLOWED_TABLES = [
        'categories',
        'announcements',
        'survey_interests_config',
        'industry_sectors_config',
        'industry_interests_config',
        'recommendation_rules',
        'domain_classifications',
    ];

    /**
     * Sensitive column names that must never be exposed through table data endpoints.
     */
    private const SENSITIVE_COLUMNS = [
        'password',
        'remember_token',
        'secret',
        'token',
        'api_key',
        'webhook_secret',
    ];

    public function getTables(Request $request)
    {
        if ($request->user()->role !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized. Only Super Admin can manage tables.'], 403);
        }

        $result = [];

        foreach (self::ALLOWED_TABLES as $name) {
            if (!Schema::hasTable($name)) {
                continue;
            }

            try {
                $count = DB::table($name)->count();
            } catch (\Exception $e) {
                $count = 0;
            }

            $result[] = [
                'name' => $name,
                'rows_count' => $count
            ];
        }

        return response()->json($result);
    }

    public function getTableData(Request $request, $tableName)
    {
        if ($request->user()->role !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        // Strict allowlist validation
        if (!in_array($tableName, self::ALLOWED_TABLES, true)) {
            return response()->json([
                'message' => 'Access denied. The requested table is protected by system security policies.'
            ], 403);
        }

        if (!Schema::hasTable($tableName)) {
            return response()->json(['message' => 'Table not found.'], 404);
        }

        $allColumns = Schema::getColumnListing($tableName);
        $safeColumns = array_values(array_diff($allColumns, self::SENSITIVE_COLUMNS));

        $data = DB::table($tableName)
            ->select($safeColumns)
            ->orderBy('id', 'desc')
            ->limit(200)
            ->get();

        return response()->json([
            'table' => $tableName,
            'columns' => $safeColumns,
            'data' => $data
        ]);
    }

    public function deleteRecord(Request $request, $tableName, $id)
    {
        if ($request->user()->role !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        // Strict allowlist validation
        if (!in_array($tableName, self::ALLOWED_TABLES, true)) {
            return response()->json([
                'message' => 'Access denied. Deletion is restricted for this table.'
            ], 403);
        }

        if (!Schema::hasTable($tableName)) {
            return response()->json(['message' => 'Table not found.'], 404);
        }

        try {
            $deleted = DB::table($tableName)->where('id', $id)->delete();
            if ($deleted) {
                ActivityLog::log(
                    $request->user()->id,
                    'Deleted record from table',
                    "Record #{$id} deleted from table '{$tableName}'",
                    'system'
                );

                return response()->json(['message' => 'Record deleted successfully.']);
            } else {
                return response()->json(['message' => 'Record not found or already deleted.'], 404);
            }
        } catch (\Exception $e) {
            \Log::error("Failed to delete record #{$id} from table {$tableName}: " . $e->getMessage());
            return response()->json(['message' => 'Failed to delete record due to database constraint.'], 500);
        }
    }
}
