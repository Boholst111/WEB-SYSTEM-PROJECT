# Requirements Document

## Introduction

Diecast Empire is a specialized e-commerce platform designed specifically for scale model collectors and the diecast hobby market. The system handles unique aspects of scale model collecting including various scales, materials, chase variants, pre-orders with deposit systems, and loyalty rewards. Built on React.js frontend with PHP/Laravel backend and MySQL database, the platform must support 10,000+ SKUs while maintaining high performance during peak traffic events.

## Glossary

- **Diecast_Empire_System**: The complete e-commerce platform including frontend, backend, database, and all integrated modules
- **Catalog_Module**: The product browsing and filtering system for scale models
- **Pre_Order_Engine**: The system managing future releases, deposits, and arrival notifications
- **Loyalty_System**: The Diecast Credits reward and redemption mechanism
- **Admin_Dashboard**: The administrative interface for analytics, reporting, and order management
- **User**: A registered customer who can browse, purchase, and earn credits
- **Administrator**: A system operator with access to dashboard, analytics, and order management
- **SKU**: Stock Keeping Unit - a unique product identifier
- **Chase_Variant**: A rare or limited edition version of a scale model
- **Drop_Day**: A scheduled release event that generates high concurrent traffic
- **LCP**: Largest Contentful Paint - a web performance metric measuring load time
- **Scale**: The size ratio of the model to the real vehicle (e.g., 1:64, 1:43, 1:18)
- **Diecast_Credits**: Loyalty points earned from purchases that can be redeemed as discounts
- **Payment_Gateway**: Third-party payment processor (GCash, Maya, Bank Transfer)

