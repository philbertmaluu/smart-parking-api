# Smart Parking System - Authentication Documentation

## Overview
This document describes the authentication system for the Smart Parking System, including user registration, login, operator management, and password reset functionality.

## Features

### 🔐 **Authentication Features**
- User registration with role selection
- Secure login with token-based authentication
- Password change and reset functionality
- Token refresh mechanism
- Profile management

### 👥 **Operator Management**
- Create new operators with role assignment
- Activate/deactivate operators
- Update operator profiles
- Reset operator passwords
- View operator statistics

### 🛡️ **Security Features**
- Role-based access control (RBAC)
- Permission-based authorization
- Password hashing with bcrypt
- Token-based authentication with Laravel Sanctum
- Account activation/deactivation
- Self-deletion prevention

## API Endpoints

### Public Endpoints

#### 1. User Registration
```http
POST /api/register
```

**Request Body:**
```json
{
    "username": "john_doe",
    "email": "john@example.com",
    "phone": "+1234567890",
    "password": "password123",
    "password_confirmation": "password123",
    "address": "123 Main St, City, State",
    "gender": "male",
    "date_of_birth": "1990-01-01",
    "role_id": 3
}
```

**Response:**
```json
{
    "success": true,
    "data": {
        "user": {
            "id": 1,
            "username": "john_doe",
            "email": "john@example.com",
            "role": {
                "id": 3,
                "name": "Gate Operator"
            },
            "permissions": [...]
        },
        "token": "1|abc123...",
        "token_type": "Bearer"
    },
    "messages": "User registered successfully",
    "status": 201
}
```

#### 2. User Login
```http
POST /api/login
```

**Request Body:**
```json
{
    "email": "john@example.com",
    "password": "password123"
}
```

**Response:**
```json
{
    "success": true,
    "data": {
        "user": {
            "id": 1,
            "username": "john_doe",
            "email": "john@example.com",
            "role": {...},
            "permissions": [...]
        },
        "token": "1|abc123...",
        "token_type": "Bearer"
    },
    "messages": "Login successful"
}
```

#### 3. Forgot Password
```http
POST /api/forgot-password
```

**Request Body:**
```json
{
    "email": "john@example.com"
}
```

#### 4. Reset Password
```http
POST /api/reset-password
```

**Request Body:**
```json
{
    "token": "reset_token_here",
    "email": "john@example.com",
    "password": "newpassword123",
    "password_confirmation": "newpassword123"
}
```

### Protected Endpoints

All protected endpoints require the `Authorization: Bearer {token}` header.

#### 1. Get Current User
```http
GET /api/toll-v1/user
```

#### 2. Update Profile
```http
PUT /api/toll-v1/profile
```

**Request Body:**
```json
{
    "username": "john_doe_updated",
    "phone": "+1234567891",
    "address": "456 New St, City, State"
}
```

#### 3. Change Password
```http
POST /api/toll-v1/change-password
```

**Request Body:**
```json
{
    "current_password": "oldpassword123",
    "new_password": "newpassword123",
    "new_password_confirmation": "newpassword123"
}
```

#### 4. Logout
```http
POST /api/toll-v1/logout
```

#### 5. Refresh Token
```http
POST /api/toll-v1/refresh-token
```

### Operator Management Endpoints

#### 1. Get All Operators
```http
GET /api/toll-v1/operators?per_page=15&search=john&role_id=3&is_active=1
```

#### 2. Create Operator
```http
POST /api/toll-v1/operators
```

**Request Body:**
```json
{
    "username": "new_operator",
    "email": "operator@example.com",
    "phone": "+1234567890",
    "password": "password123",
    "password_confirmation": "password123",
    "address": "123 Operator St, City, State",
    "gender": "female",
    "date_of_birth": "1995-05-15",
    "role_id": 3
}
```

#### 3. Get Operator by ID
```http
GET /api/toll-v1/operators/{id}
```

#### 4. Update Operator
```http
PUT /api/toll-v1/operators/{id}
```

#### 5. Activate Operator
```http
POST /api/toll-v1/operators/{id}/activate
```

#### 6. Deactivate Operator
```http
POST /api/toll-v1/operators/{id}/deactivate
```

#### 7. Reset Operator Password
```http
POST /api/toll-v1/operators/{id}/reset-password
```

**Request Body:**
```json
{
    "new_password": "newpassword123",
    "new_password_confirmation": "newpassword123"
}
```

#### 8. Delete Operator
```http
DELETE /api/toll-v1/operators/{id}
```

### Role Management

#### Get Available Roles
```http
GET /api/toll-v1/roles
```

## Form Request Validation

### RegisterRequest
- `username`: Required, unique, max 255 characters
- `email`: Required, valid email, unique, max 255 characters
- `phone`: Required, max 20 characters
- `password`: Required, min 8 characters, confirmed
- `address`: Required, max 500 characters
- `gender`: Required, one of: male, female, other
- `date_of_birth`: Required, valid date, before today
- `role_id`: Optional, must exist in roles table

### LoginRequest
- `email`: Required, valid email
- `password`: Required

### UpdateUserRequest
- `username`: Optional, unique (excluding current user), max 255 characters
- `email`: Optional, valid email, unique (excluding current user), max 255 characters
- `phone`: Optional, max 20 characters
- `address`: Optional, max 500 characters
- `gender`: Optional, one of: male, female, other
- `date_of_birth`: Optional, valid date, before today

### ChangePasswordRequest
- `current_password`: Required
- `new_password`: Required, min 8 characters, confirmed, different from current

### ResetPasswordRequest
- `token`: Required
- `email`: Required, valid email, must exist
- `password`: Required, min 8 characters, confirmed

## Error Responses

### Validation Errors
```json
{
    "success": false,
    "message": "Validation Error",
    "data": {
        "email": ["The email field is required."],
        "password": ["The password must be at least 8 characters."]
    }
}
```

### Authentication Errors
```json
{
    "success": false,
    "message": "Invalid credentials"
}
```

### Authorization Errors
```json
{
    "success": false,
    "message": "Unauthorized"
}
```

### Server Errors
```json
{
    "success": false,
    "message": "Login failed",
    "data": "Error details..."
}
```

## Usage Examples

### JavaScript/Fetch Example
```javascript
// Login
const loginResponse = await fetch('/api/login', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
    },
    body: JSON.stringify({
        email: 'admin@smartparking.com',
        password: 'password123'
    })
});

const loginData = await loginResponse.json();
const token = loginData.data.token;

// Use token for authenticated requests
const userResponse = await fetch('/api/toll-v1/user', {
    headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json',
    }
});
```

### cURL Example
```bash
# Login
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@smartparking.com","password":"password123"}'

# Get user with token
curl -X GET http://localhost:8000/api/toll-v1/user \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

## Security Considerations

### Password Security
- Passwords are hashed using bcrypt
- Minimum 8 characters required
- Password confirmation required for changes
- Current password verification for changes

### Token Security
- Tokens are automatically revoked on password change
- Tokens are revoked on account deactivation
- Token refresh mechanism available
- Self-deletion prevention implemented

### Role-Based Access
- All operator management requires appropriate permissions
- Users can only perform actions they have permissions for
- Role hierarchy enforced (System Admin > Stations Manager > Gate Operator)

## Default Users

After running seeders, the following users are available:

| Email | Password | Role |
|-------|----------|------|
| admin@smartparking.com | password123 | System Administrator |
| manager@smartparking.com | password123 | Stations Manager |
| operator1@smartparking.com | password123 | Gate Operator |
| operator2@smartparking.com | password123 | Gate Operator |
| operator3@smartparking.com | password123 | Gate Operator |

## Testing

### Test Registration
```bash
curl -X POST http://localhost:8000/api/register \
  -H "Content-Type: application/json" \
  -d '{
    "username": "testuser",
    "email": "test@example.com",
    "phone": "+1234567890",
    "password": "password123",
    "password_confirmation": "password123",
    "address": "123 Test St",
    "gender": "male",
    "date_of_birth": "1990-01-01",
    "role_id": 3
  }'
```

### Test Login
```bash
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@smartparking.com",
    "password": "password123"
  }'
```

## Troubleshooting

### Common Issues

1. **Token Expired**: Use refresh token endpoint
2. **Invalid Credentials**: Check email and password
3. **Account Deactivated**: Contact administrator
4. **Permission Denied**: Check user role and permissions
5. **Validation Errors**: Review request body format

### Debug Mode
Enable debug mode in `.env` for detailed error messages:
```
APP_DEBUG=true
``` 
