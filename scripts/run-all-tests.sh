#!/bin/bash

# Diecast Empire - Comprehensive Test Runner
# Runs all tests including unit, feature, property-based, and integration tests

set -e

echo "========================================="
echo "Diecast Empire - Test Suite"
echo "========================================="

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
BLUE='\033[0;34m'
NC='\033[0m'

# Test results
TOTAL_TESTS=0
PASSED_TESTS=0
FAILED_TESTS=0

# Function to print section header
print_section() {
    echo ""
    echo -e "${BLUE}=========================================${NC}"
    echo -e "${BLUE}$1${NC}"
    echo -e "${BLUE}=========================================${NC}"
}

# Function to run test suite
run_test_suite() {
    local suite_name=$1
    local test_command=$2
    
    echo ""
    echo -e "${YELLOW}Running $suite_name...${NC}"
    
    if eval "$test_command"; then
        echo -e "${GREEN}✓ $suite_name PASSED${NC}"
        return 0
    else
        echo -e "${RED}✗ $suite_name FAILED${NC}"
        return 1
    fi
}

# Change to backend directory
cd backend

print_section "1. Unit Tests"
if run_test_suite "Unit Tests" "php artisan test --testsuite=Unit"; then
    ((PASSED_TESTS++))
else
    ((FAILED_TESTS++))
fi
((TOTAL_TESTS++))

print_section "2. Feature Tests"
if run_test_suite "Feature Tests" "php artisan test --testsuite=Feature --exclude-group=property"; then
    ((PASSED_TESTS++))
else
    ((FAILED_TESTS++))
fi
((TOTAL_TESTS++))

print_section "3. Property-Based Tests"
echo ""
echo "Testing all 6 correctness properties..."

# Property 1: Product Filtering Accuracy
echo ""
echo -e "${YELLOW}Property 1: Product Filtering Accuracy${NC}"
if run_test_suite "Property 1" "php artisan test --filter=ProductFilteringAccuracyPropertyTest"; then
    ((PASSED_TESTS++))
else
    ((FAILED_TESTS++))
fi
((TOTAL_TESTS++))

# Property 2: Pre-order Payment Flow Integrity
echo ""
echo -e "${YELLOW}Property 2: Pre-order Payment Flow Integrity${NC}"
if run_test_suite "Property 2" "php artisan test --filter=PreOrderPaymentFlowIntegrityPropertyTest"; then
    ((PASSED_TESTS++))
else
    ((FAILED_TESTS++))
fi
((TOTAL_TESTS++))

# Property 3: Loyalty Credits Ledger Accuracy
echo ""
echo -e "${YELLOW}Property 3: Loyalty Credits Ledger Accuracy${NC}"
if run_test_suite "Property 3" "php artisan test --filter=LoyaltyCreditsLedgerAccuracyPropertyTest"; then
    ((PASSED_TESTS++))
else
    ((FAILED_TESTS++))
fi
((TOTAL_TESTS++))

# Property 4: Payment Gateway Transaction Integrity
echo ""
echo -e "${YELLOW}Property 4: Payment Gateway Transaction Integrity${NC}"
if run_test_suite "Property 4" "php artisan test --filter=PaymentGatewayTransactionIntegrityPropertyTest"; then
    ((PASSED_TESTS++))
else
    ((FAILED_TESTS++))
fi
((TOTAL_TESTS++))

# Property 5: User Authentication Security
echo ""
echo -e "${YELLOW}Property 5: User Authentication Security${NC}"
if run_test_suite "Property 5" "php artisan test --filter=AuthenticationSecurityPropertyTest"; then
    ((PASSED_TESTS++))
else
    ((FAILED_TESTS++))
fi
((TOTAL_TESTS++))

# Property 6: Inventory Stock Consistency
echo ""
echo -e "${YELLOW}Property 6: Inventory Stock Consistency${NC}"
if run_test_suite "Property 6" "php artisan test --filter=InventoryStockConsistencyPropertyTest"; then
    ((PASSED_TESTS++))
else
    ((FAILED_TESTS++))
fi
((TOTAL_TESTS++))

print_section "4. Integration Tests"
if run_test_suite "System Integration Tests" "php artisan test --filter=SystemIntegrationTest"; then
    ((PASSED_TESTS++))
else
    ((FAILED_TESTS++))
fi
((TOTAL_TESTS++))

if run_test_suite "End-to-End Tests" "php artisan test --filter=EndToEndUserJourneyTest"; then
    ((PASSED_TESTS++))
else
    ((FAILED_TESTS++))
fi
((TOTAL_TESTS++))

print_section "5. Performance Tests"
if run_test_suite "Performance Tests" "php artisan test --filter=PerformanceIntegrationTest"; then
    ((PASSED_TESTS++))
else
    ((FAILED_TESTS++))
fi
((TOTAL_TESTS++))

# Return to root directory
cd ..

# Frontend tests
print_section "6. Frontend Tests"
cd frontend
if run_test_suite "Frontend Tests" "npm test -- --watchAll=false --coverage"; then
    ((PASSED_TESTS++))
else
    ((FAILED_TESTS++))
fi
((TOTAL_TESTS++))
cd ..

# Print summary
echo ""
echo "========================================="
echo "Test Suite Summary"
echo "========================================="
echo -e "Total Test Suites: $TOTAL_TESTS"
echo -e "${GREEN}Passed: $PASSED_TESTS${NC}"
echo -e "${RED}Failed: $FAILED_TESTS${NC}"
echo ""

if [ $FAILED_TESTS -eq 0 ]; then
    echo -e "${GREEN}✓ All test suites passed!${NC}"
    echo ""
    echo "All 6 correctness properties validated:"
    echo "  ✓ Property 1: Product Filtering Accuracy"
    echo "  ✓ Property 2: Pre-order Payment Flow Integrity"
    echo "  ✓ Property 3: Loyalty Credits Ledger Accuracy"
    echo "  ✓ Property 4: Payment Gateway Transaction Integrity"
    echo "  ✓ Property 5: User Authentication Security"
    echo "  ✓ Property 6: Inventory Stock Consistency"
    echo ""
    echo "========================================="
    exit 0
else
    echo -e "${RED}✗ Some test suites failed!${NC}"
    echo "========================================="
    exit 1
fi
