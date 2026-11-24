# CODL Frontend: Modern Distance Learning Client

The frontend for CODL-SUSL is built with **React 18**, **TypeScript**, and **Vite**, styled with **TailwindCSS** and powered by **Lucide React** icons.

---

## Key Capabilities

- **Role-Tailored Portals**: Dynamic UI experiences adapted to user roles (`super_admin`, `director`, `coordinator`, `secretary`, `lecturer`, and `student`).
- **AI Analytics & Industry Alignment Dashboard**: An interactive visual dashboard displaying student demand distributions, industry skill gap matrices, geographic demand heatmaps, and course-by-course curriculum alignment coverage.
- **Course & Batch Management**: Program directors and coordinators can manage semester structures, subject credits, instructor allocations, and student enrollment rosters.
- **Examination Workflows**: Students can register for exams, request postponements, or view verified exam grades and official letters.

---

## Project Structure

```
frontend/
├── src/
│   ├── assets/            # Static assets and university branding
│   ├── components/        # Reusable UI components & dialogs
│   │   └── AIAnalytics.tsx# AI curriculum alignment & industry gap dashboard
│   ├── context/           # React context providers (AuthContext, ThemeContext)
│   ├── pages/             # Route-level page views (Dashboard, Courses, Admin, Exams)
│   ├── services/          # API client integration layer
│   └── types/             # TypeScript interface definitions
├── index.html
├── vite.config.ts
└── tailwind.config.js
```

---

## Development Setup

1. **Install dependencies**:
   ```bash
   npm install
   ```

2. **Run local development server**:
   ```bash
   npm run dev
   ```

3. **Build production bundle**:
   ```bash
   npm run build
   ```
