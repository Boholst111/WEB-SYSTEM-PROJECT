#!/bin/bash

# Diecast Empire - System Validation Script
# Validates all system components are working correctly

set -e

echo "========================================="
echo "Diecast Empire - System Validation"
echo "========================================="

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

PASSED=0
FAILED=0

# Function to run validation check
run_check() {
    local check_name=$1
    local check_command=$2
    
    echo -n "Checking $check_name... "
    
    if eval "$check_command" > /dev/null 2>&1; then
        echo -e "${GREEN}✓ PASSED${NC}"
        ((PASSED++))
        return 0
    else
        echo -e "${RED}✗ FAILED${NC}"
        ((FAILED++))
        return 1
    fi
}

echo ""
echo "=== Infrastructure Checks ==="

# Check Docker containers
run_check "Docker containers" "docker ps | grep -q diecast"

# Check MySQL
run_check "MySQL connection" "docker exec diecast_mysql_primary mysqladmin ping -h localhost -u root -proot_password"

# Check Redis
run_check "Redis connection" "docker exec diecast_redis redis-cli ping | grep -q PONG"

# Check Nginx
run_check "Nginx service" "curl -s -o /dev/null -w '%{http_code}' http://localhost:8080 | grep -q 200"

echo ""
echo "=== Backend API Checks ==="

# Check health endpoint
run_check "Health endpoint" "curl -s http://localhost:8080/api/health | grep -q '\"status\":\"ok\"'"

# Check products endpoint
run_check "Products API" "curl -s http://localhost:8080/api/products | grep -q '\"data\"'"

# Check categories endpoint
run_check "Categories API" "curl -s http://localhost:8080/api/categories | grep -q '\"data\"'"

# Check brands endpoint
run_check "Brands API" "curl -s http://localhost:8080/api/brands | grep -q '\"data\"'"

# Check filters endpoint
run_check "Filters API" "curl -s http://localhost:8080/api/filters | grep -q 'scales'"

echo ""
echo "=== Frontend Checks ==="

# Check frontend is running
run_check "Frontend service" "curl -s -o /dev/null -w '%{http_code}' http://localhost:3000 | grep -q 200"

echo ""
echo "=== Database Checks ==="

# Check database tables exist
run_check "Products table" "docker exec diecast_mysql_primary mysql -u root -proot_password -e 'USE diecast_empire_db; SELECT COUNT(*) FROM products;' | grep -q '[0-9]'"

run_check "Users table" "docker exec diecast_mysql_primary mysql -u root -proot_password -e 'USE diecast_empire_db; SELECT COUNT(*) FROM users;' | grep -q '[0-9]'"

run_check "Orders table" "docker exec diecast_mysql_primary mysql -u root -proot_password -e 'USE diecast_empire_db; SELECT COUNT(*) FROM orders;' | grep -q '[0-9]'"

echo ""
echo "=== Cache Checks ==="

# Check Redis cache
run_check "Redis cache" "docker exec diecast_redis redis-cli SET test_key test_value && docker exec diecast_redis redis-cli GET test_key | grep -q test_value"

# Cleanup test key
docker exec diecast_redis redis-cli DEL test_key > /dev/null 2>&1

echo ""
echo "=== Security Checks ==="

# Check security headers
run_check "X-Frame-Options header" "curl -s -I http://localhost:8080/api/health | grep -q 'X-Frame-Options'"

run_check "X-Content-Type-Options header" "curl -s -I http://localhost:8080/api/health | grep -q 'X-Content-Type-Options'"

echo ""
echo "=== Performance Checks ==="

# Check response time
RESPONSE_TIME=$(curl -o /dev/null -s -w '%{time_total}' http://localhost:8080/api/products)
if (( $(echo "$RESPONSE_TIME < 2.0" | bc -l) )); then
    echo -e "Checking API response time... ${GREEN}✓ PASSED${NC} (${RESPONSE_TIME}s)"
    ((PASSED++))
else
    echo -e "Checking API response time... ${RED}✗ FAILED${NC} (${RESPONSE_TIME}s > 2.0s)"
    ((FAILED++))
fi

echo ""
echo "========================================="
echo "Validation Summary"
echo "========================================="
echo -e "Total Checks: $((PASSED + FAILED))"
echo -e "${GREEN}Passed: $PASSED${NC}"
echo -e "${RED}Failed: $FAILED${NC}"
echo ""

if [ $FAILED -eq 0 ]; then
    echo -e "${GREEN}✓ All validation checks passed!${NC}"
    echo "========================================="
    exit 0
else
    echo -e "${RED}✗ Some validation checks failed!${NC}"
    echo "========================================="
    exit 1
fi
