@echo off
echo 🚀 Setting up Diecast Empire development environment...

REM Check if Docker is installed
docker --version >nul 2>&1
if %errorlevel% neq 0 (
    echo ❌ Docker is not installed. Please install Docker first.
    exit /b 1
)

docker-compose --version >nul 2>&1
if %errorlevel% neq 0 (
    echo ❌ Docker Compose is not installed. Please install Docker Compose first.
    exit /b 1
)

REM Create necessary directories
echo 📁 Creating necessary directories...
if not exist "backend\storage\app\public" mkdir "backend\storage\app\public"
if not exist "backend\storage\framework\cache\data" mkdir "backend\storage\framework\cache\data"
if not exist "backend\storage\framework\sessions" mkdir "backend\storage\framework\sessions"
if not exist "backend\storage\framework\views" mkdir "backend\storage\framework\views"
if not exist "backend\storage\logs" mkdir "backend\storage\logs"
if not exist "backend\bootstrap\cache" mkdir "backend\bootstrap\cache"

REM Copy environment files
echo 📋 Setting up environment files...
if not exist "backend\.env" (
    copy "backend\.env.example" "backend\.env"
    echo ✅ Backend .env file created
) else (
    echo ⚠️  Backend .env file already exists
)

REM Start Docker containers
echo 🐳 Starting Docker containers...
docker-compose up -d

REM Wait for MySQL to be ready
echo ⏳ Waiting for MySQL to be ready...
timeout /t 30 /nobreak >nul

REM Install backend dependencies
echo 📦 Installing backend dependencies...
docker-compose exec -T app composer install --no-interaction --prefer-dist --optimize-autoloader

REM Generate application key
echo 🔑 Generating application key...
docker-compose exec -T app php artisan key:generate

REM Run database migrations
echo 🗄️  Running database migrations...
docker-compose exec -T app php artisan migrate --force

REM Create storage link
echo 🔗 Creating storage link...
docker-compose exec -T app php artisan storage:link

REM Clear and cache config
echo 🧹 Clearing and caching configuration...
docker-compose exec -T app php artisan config:clear
docker-compose exec -T app php artisan config:cache
docker-compose exec -T app php artisan route:cache

REM Install frontend dependencies
echo 📦 Installing frontend dependencies...
cd frontend
npm --version >nul 2>&1
if %errorlevel% equ 0 (
    npm install
    echo ✅ Frontend dependencies installed
) else (
    echo ⚠️  npm not found. Please install Node.js to set up the frontend.
)
cd ..

REM Display access information
echo.
echo 🎉 Setup complete! Your Diecast Empire development environment is ready.
echo.
echo 📍 Access URLs:
echo    Frontend:    http://localhost:3000
echo    Backend API: http://localhost:8080/api
echo    MySQL:       localhost:3306
echo    Redis:       localhost:6379
echo.
echo 🔧 Useful commands:
echo    Start services:     docker-compose up -d
echo    Stop services:      docker-compose down
echo    View logs:          docker-compose logs -f
echo    Backend shell:      docker-compose exec app bash
echo    Run migrations:     docker-compose exec app php artisan migrate
echo    Clear cache:        docker-compose exec app php artisan cache:clear
echo.
echo 📚 Next steps:
echo    1. Start the frontend: cd frontend ^&^& npm start
echo    2. Visit http://localhost:3000 to see the application
echo    3. Check the API at http://localhost:8080/api/health
echo.
pause