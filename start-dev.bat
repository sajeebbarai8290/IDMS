@echo off
echo ========================================================
echo  Integrated Disaster Management System (IDMS) Dev Server
echo  Ministry of Disaster Management and Relief (MoDMR)
echo ========================================================
echo.

echo [1/3] Ensuring Docker services (Postgres, Redis, Keycloak, pgAdmin) are running...
cd /d %~dp0deployment\docker
docker compose up -d

echo.
echo [2/3] Launching Laravel Backend API on http://localhost:8000...
start "IDMS Backend API" cmd /k "cd /d %~dp0backend && php -d extension=pdo_pgsql -d extension=pgsql artisan serve --port=8000"

echo [3/3] Launching React Frontend (Vite) on http://localhost:5173...
start "IDMS React Frontend" cmd /k "cd /d %~dp0frontend && npm run dev"

echo.
echo ========================================================
echo  All services launched!
echo.
echo  * React Frontend Portal:  http://localhost:5173
echo  * Backend REST API:        http://localhost:8000/api/v1/health
echo  * pgAdmin 4 Web GUI:       http://localhost:5050
echo  * Keycloak IAM:            http://localhost:8080
echo ========================================================
echo.
pause
