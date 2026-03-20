#!/bin/bash

# Performance Test Runner for Diecast Empire
# Validates Requirement 1.2: Handle 100-500 concurrent users with sub-2-second load times

set -e

echo "=========================================="
echo "Diecast Empire Performance Test Suite"
echo "Requirement 1.2: Drop Day Traffic Performance"
echo "=========================================="
echo ""

# Colors for output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Track test results
BACKEND_TESTS_PASSED=0
FRONTEND_TESTS_PASSED=0

# Backend Performance Tests
echo -e "${BLUE}[1/3] Running Backend Load Tests...${NC}"
echo "Testing: 500 concurrent users, API response times"
echo ""

cd backend
if php artisan test --testsuite=Performance --stop-on-failure; then
    echo -e "${GREEN}✓ Backend performance tests passed${NC}"
    BACKEND_TESTS_PASSED=1
else
    echo -e "${RED}✗ Backend performance tests failed${NC}"
fi
echo ""

cd ..

# Frontend Performance Tests
echo -e "${BLUE}[2/3] Running Frontend Performance Tests...${NC}"
echo "Testing: Core Web Vitals, page load times, concurrent operations"
echo ""

cd frontend
if npm test -- --testPathPattern=performance --run; then
    echo -e "${GREEN}✓ Frontend performance tests passed${NC}"
    FRONTEND_TESTS_PASSED=1
else
    echo -e "${RED}✗ Frontend performance tests failed${NC}"
fi
echo ""

cd ..

# Summary
echo "=========================================="
echo -e "${BLUE}[3/3] Performance Test Summary${NC}"
echo "=========================================="
echo ""

if [ $BACKEND_TESTS_PASSED -eq 1 ]; then
    echo -e "${GREEN}✓ Backend Load Tests: PASSED${NC}"
    echo "  - Product catalog: < 2s response time"
    echo "  - Database queries: < 200ms"
    echo "  - Concurrent users: 100-500 handled"
else
    echo -e "${RED}✗ Backend Load Tests: FAILED${NC}"
fi
echo ""

if [ $FRONTEND_TESTS_PASSED -eq 1 ]; then
    echo -e "${GREEN}✓ Frontend Performance Tests: PASSED${NC}"
    echo "  - LCP: < 2.5s"
    echo "  - FID: < 100ms"
    echo "  - CLS: < 0.1"
else
    echo -e "${RED}✗ Frontend Performance Tests: FAILED${NC}"
fi
echo ""

# Overall result
if [ $BACKEND_TESTS_PASSED -eq 1 ] && [ $FRONTEND_TESTS_PASSED -eq 1 ]; then
    echo -e "${GREEN}=========================================="
    echo "✓ ALL PERFORMANCE TESTS PASSED"
    echo "Requirement 1.2: VALIDATED"
    echo -e "==========================================${NC}"
    exit 0
else
    echo -e "${RED}=========================================="
    echo "✗ SOME PERFORMANCE TESTS FAILED"
    echo "Requirement 1.2: NOT VALIDATED"
    echo -e "==========================================${NC}"
    exit 1
fi
