# KALT — Automated Sports Nutrition Platform

KALT is a high-performance, deterministic automated sports nutrition web application designed to act as an automated digital nutritionist. It generates customized weekly meal plans tailored to specific fitness goals (definition, maintenance, hypertrophy/volume), monitors daily macronutrient compliance, and dynamically recalculates targets in real-time when facing daily anomalies like skipped meals or extra workouts.

The platform is built around an editorial "AI lab zine" visual ecosystem, leveraging a high-contrast design with a unique typographic approach and clean structured boundaries.

---

## 🛠️ Technical Stack

| Layer | Technology |
|---|---|
| **Frontend Framework** | Vue 3 + Vite |
| **State Management** | Pinia |
| **Routing** | Vue Router |
| **Styling Architecture** | Tailwind CSS v4 (Design system tokens embedded) |
| **Charts & Heatmaps** | Chart.js + Custom inline SVG Adherence Canvas |
| **Backend Framework** | Laravel 11 (PHP 8.3) |
| **Primary Database** | PostgreSQL 16 |
| **Cache & Queue Broker** | Redis 7 |
| **Authentication Strategy**| Laravel Sanctum (Stateful HTTP-Only Cookie Mode — No LocalStorage) |
| **Testing Engine** | Pest Testing Framework |

---

## 📦 Core Architecture & Feature Set

### 1. Deterministic TDEE & Macro Engine
- **Target Tracking**: Calculates baseline metrics via the Harris-Benedict formula and applies precise multipliers depending on activity levels.
- **Goal Allocations**: Adjusts calorie thresholds (e.g., Hypertrophy: Target + 300 kcal, Cutting: Target - 300 kcal) paired with fixed multi-split macronutrient breakdowns.
- **Slot Distribution**: Automatically maps precise intake metrics separating pre-workout, post-workout, and baseline meal configurations.

### 2. Dynamic Asynchronous Generation (`GenerateDietJob`)
- Dispatches queued background processes through Redis to map valid structural meal ingredients matching target requirements.
- Implements rigorous backend checks against user allergies, treating restriction profiles as unbypassable blocks.

### 3. Real-Time Variable Recalculator (`DayRecalculator`)
Handles unexpected daily logging events gracefully via non-intrusive contextual dashboard alerts (`RecalcNotice.vue`):
- **Case A (Skipped Meal)**: Proportions remaining daily macronutrient margins evenly across unconsumed planned meals.
- **Case B (Missed Workout)**: Trims down active day carbohydrate values to compensate for reduced energy expenditure.
- **Case C (Unplanned Training)**: Dynamically injects strategic energy spikes into surrounding eating slots.
- **Case D (Extra Feed)**: Subtracts newly tracked items from subsequent meal values to keep daily targets balanced.

### 4. Custom Signature Interface Components
- **`MacroBar.vue`**: A specialized segmented visualization tracker providing macro allocations ("Xg / Yg") and real-time transition animations.
- **`AdherenceHeatmap.vue`**: A bespoke inline SVG activity matrix rendering contextual historical compliance tracking without bulky third-party visual overhead.

---

## 🐳 Local Development Infrastructure

The development lifecycle runs completely containerized using Docker Compose, establishing five key services: `app` (Laravel), `postgres`, `redis`, `queue-worker`, and `vite` (Vue development server).

### Port Mapping Reference
- **Frontend SPA**: `http://localhost:5173`
- **Backend Core API**: `http://localhost:8000`
- **PostgreSQL Database**: `localhost:5432`
- **Redis Cache Instance**: `localhost:6379`

---

## 🚀 Step-by-Step Local Deployment

Follow these sequential operations inside your terminal execution layer to spin up your local environment:

### 1. Environment Preparation
Clone your local working tree, open the root workspace directory, and duplicate the default environmental configuration map:
```bash
cp backend/.env.example backend/.env
```

### 2. Bootstrapping Containers
Spin up the Docker architecture. This command will pull necessary images and start all 5 services:
```bash
docker compose up -d
```

### 3. Backend Initialization
Install PHP dependencies, generate the application key, and migrate the database schema:
```bash
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

### 4. Frontend Initialization
Install Node modules and spin up the Vite development server (which is already configured to run inside the container, but you can also run it locally if preferred):
```bash
cd frontend
npm install
npm run dev
```

### 5. Running the Test Suite
Ensure the platform integrity is maintained by executing the Pest test suite:
```bash
docker compose exec app php artisan test
```

---

## 🎨 Design System

Please refer to the `DESIGN.md` file for full documentation regarding the typographical hierarchy, macro-specific color palette, and layout constraints.
