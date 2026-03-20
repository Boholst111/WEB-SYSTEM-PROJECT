@echo off
REM Performance Test Runner for Diecast Empire (Windows)
REM Validates Requirement 1.2: Handle 100-500 concurrent users with sub-2-second load times

echo ==========================================
echo Diecast Empire Performance Test Suite
echo Requirement 1.2: Drop Day Traffic Performance
echo ==========================================
echo.

set BACKEND_TESTS_PASSED=0
set FRONTEND_TESTS_PASSED=0

REM Backend Performance Tests
echo [1/3] Running Backend Load Tests...
echo Testing: 500 concurrent users, API response times
echo.

cd backend
php artisan test --testsuite=Performance --stop-on-failure
if %ERRORLEVEL% EQU 0 (
    echo [SUCCESS] Backend performance tests passed
    set BACKEND_TESTS_PASSED=1
) else (
    echo [FAILED] Backend performance tests failed
)
echo.

cd ..

REM Frontend Performance Tests
echo [2/3] Running Frontend Performance Tests...
echo Testing: Core Web Vitals, page load times, concurrent operations
echo.

cd frontend
call npm test -- --testPathPattern=performance --run
if %ERRORLEVEL% EQU 0 (
    echo [SUCCESS] Frontend performance tests passed
    set FRONTEND_TESTS_PASSED=1
) else (
    echo [FAILED] Frontend performance tests failed
)
echo.

cd ..

REM Summary
echo ==========================================
echo [3/3] Performance Test Summary
echo ==========================================
echo.

if %BACKEND_TESTS_PASSED% EQU 1 (
    echo [PASS] Backend Load Tests: PASSED
    echo   - Product catalog: ^< 2s response time
    echo   - Database queries: ^< 200ms
    echo   - Concurrent users: 100-500 handled
) else (
    echo [FAIL] Backend Load Tests: FAILED
)
echo.

if %FRONTEND_TESTS_PASSED% EQU 1 (
    echo [PASS] Frontend Performance Tests: PASSED
    echo   - LCP: ^< 2.5s
    echo   - FID: ^< 100ms
    echo   - CLS: ^< 0.1
) else (
    echo [FAIL] Frontend Performance Tests: FAILED
)
echo.

REM Overall result
if %BACKEND_TESTS_PASSED% EQU 1 if %FRONTEND_TESTS_PASSED% EQU 1 (
    echo ==========================================
    echo [SUCCESS] ALL PERFORMANCE TESTS PASSED
    echo Requirement 1.2: VALIDATED
    echo ==========================================
    exit /b 0
) else (
    echo ==========================================
    echo [FAILED] SOME PERFORMANCE TESTS FAILED
    echo Requirement 1.2: NOT VALIDATED
    echo ==========================================
    exit /b 1
)
