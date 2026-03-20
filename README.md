# Diecast Empire E-commerce Platform

A specialized e-commerce platform designed for scale model collectors and the diecast hobby market. Built with React.js frontend, PHP/Laravel backend, MySQL database, and Redis caching.

## Features

- **Specialized Product Catalog**: Support for 10,000+ SKUs with complex filtering by scale, material, features, and chase variants
- **Pre-order Management**: Robust system for managing future releases with deposit/full payment options
- **Loyalty Credits System**: Comprehensive rewards system with transaction ledger and tier progression
- **High Performance**: Optimized for Drop Day events with 100-500 concurrent users
- **Payment Integration**: Secure integration with Philippine payment gateways (GCash, Maya, Bank Transfer)
- **Admin Dashboard**: Advanced analytics, order management, and inventory tracking

## Technology Stack

### Frontend
- React.js 18 with TypeScript
- Redux Toolkit for state management
- React Router for navigation
- Tailwind CSS for styling
- React Query for API state management
- Framer Motion for animations

### Backend
- PHP 8.2 with Laravel 10
- MySQL 8.0 with read replicas
- Redis for caching and sessions
- Laravel Sanctum for API authentication
- Spatie packages for permissions and query building

### Infrastructure
- Docker containers for development
- Nginx web server
- Connection pooling for database optimization
- CDN integration for static assets

## Quick Start

### Prerequisites
- Docker and Docker Compose
- Node.js 18+ (for local frontend development)
- PHP 8.2+ (for local backend development)

### Development Setup

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd diecast-empire
   ```

2. **Start the development environment**
   ```bash
   docker-compose up -d
   ```

3. **Set up the backend**
   ```bash
   # Copy environment file
   cp backend/.env.example backend/.env
   
   # Install dependencies
   docker-compose exec app composer install
   
   # Generate application key
   docker-compose exec app php artisan key:generate
   
   # Run migrations
   docker-compose exec app php artisan migrate
   
   # Seed the database
   docker-compose exec app php artisan db:seed
   ```

4. **Set up the frontend**
   ```bash
   # Install dependencies
   cd frontend
   npm install
   
   # Start development server (if not using Docker)
   npm start
   ```

### Access the Application

- **Frontend**: http://localhost:3000
- **Backend API**: http://localhost:8080/api
- **MySQL**: localhost:3306
- **Redis**: localhost:6379

## Project Structure

```
diecast-empire/
├── backend/                 # Laravel API backend
│   ├── app/
│   │   ├── Http/Controllers/
│   │   ├── Models/
│   │   ├── Services/
│   │   └── ...
│   ├── config/
│   ├── database/
│   │   ├── migrations/
│   │   └── seeders/
│   ├── routes/
│   └── tests/
├── frontend/                # React.js frontend
│   ├── src/
│   │   ├── components/
│   │   ├── pages/
│   │   ├── store/
│   │   ├── services/
│   │   ├── types/
│   │   └── utils/
│   └── public/
├── docker/                  # Docker configuration
│   ├── nginx/
│   └── mysql/
└── docker-compose.yml
```

## API Documentation

The API follows RESTful conventions with the following main endpoints:

### Authentication
- `POST /api/auth/register` - User registration
- `POST /api/auth/login` - User login
- `POST /api/auth/logout` - User logout
- `GET /api/auth/user` - Get authenticated user

### Products
- `GET /api/products` - List products with filtering
- `GET /api/products/{id}` - Get product details
- `GET /api/categories` - List categories
- `GET /api/brands` - List brands

### Cart & Orders
- `GET /api/cart` - Get user's cart
- `POST /api/cart/items` - Add item to cart
- `PUT /api/cart/items/{id}` - Update cart item
- `DELETE /api/cart/items/{id}` - Remove cart item
- `POST /api/orders` - Create order
- `GET /api/orders` - List user's orders

### Pre-orders
- `GET /api/preorders` - List user's pre-orders
- `POST /api/preorders/{id}/deposit` - Pay deposit
- `POST /api/preorders/{id}/complete` - Complete payment

### Loyalty
- `GET /api/loyalty/balance` - Get credits balance
- `GET /api/loyalty/transactions` - Get transaction history
- `POST /api/loyalty/redeem` - Redeem credits

## Development Guidelines

### Code Style
- Follow PSR-12 coding standards for PHP
- Use ESLint and Prettier for JavaScript/TypeScript
- Write descriptive commit messages
- Include unit tests for new features

### Database
- Use migrations for all schema changes
- Include proper indexes for performance
- Follow naming conventions (snake_case for tables/columns)
- Add foreign key constraints where appropriate

### Caching Strategy
- Cache product data for 2 hours
- Cache user sessions in Redis
- Implement cache invalidation on data updates
- Use cache tags for efficient clearing

### Testing
- Write unit tests for business logic
- Include integration tests for API endpoints
- Use property-based testing for complex algorithms
- Maintain minimum 80% code coverage

## Performance Optimization

### Database Optimization
- Proper indexing for filtering queries
- Read replicas for query distribution
- Connection pooling
- Query optimization and monitoring

### Caching
- Redis for frequently accessed data
- CDN for static assets
- Browser caching headers
- API response caching

### Frontend Optimization
- Code splitting and lazy loading
- Image optimization and lazy loading
- Service worker for offline functionality
- Performance monitoring with Core Web Vitals

## Security

### Authentication & Authorization
- JWT tokens with Laravel Sanctum
- Role-based access control
- API rate limiting
- CSRF protection

### Data Protection
- Input validation and sanitization
- SQL injection prevention
- XSS protection
- Secure password hashing

### Payment Security
- PCI DSS compliance considerations
- Secure API key management
- Transaction encryption
- Fraud detection measures

## Deployment

### Production Environment
- Use environment-specific configuration
- Enable SSL/TLS certificates
- Configure proper logging
- Set up monitoring and alerting
- Implement backup strategies

### CI/CD Pipeline
- Automated testing on pull requests
- Code quality checks
- Automated deployment to staging
- Manual approval for production deployment

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests for new functionality
5. Ensure all tests pass
6. Submit a pull request

## License

This project is proprietary software. All rights reserved.

## Support

For technical support or questions, please contact the development team.