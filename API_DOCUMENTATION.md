# API Documentation

This document provides a comprehensive overview of all available API endpoints in the Cloud application.

## Base URL
All API endpoints are prefixed with `/api`

## Authentication
- **JWT Token**: Most endpoints require authentication via JWT token
- **Authorization Header**: Include `Authorization: Bearer <token>` in request headers
- **Token Format**: JWT tokens are obtained through the login endpoint

---

## Authentication Endpoints

### 1. User Registration
**Endpoint:** `POST /api/auth/register`
**Authentication:** Not required
**Description:** Register a new user account

**Request Payload:**
```json
{
    "username": "string (required)",
    "email": "string (required, valid email format)",
    "password": "string (required)"
}
```

**Response (201 Created):**
```json
{
    "message": "User registered successfully",
    "user": {
        "id": "integer",
        "username": "string",
        "email": "string"
    }
}
```

**Error Responses:**
- `400 Bad Request`: Missing required fields or validation errors
- `409 Conflict`: Username or email already exists

---

### 2. User Login
**Endpoint:** `POST /api/auth/login`
**Authentication:** Not required
**Description:** Authenticate user and receive JWT token

**Request Payload:**
```json
{
    "username": "string (required)",
    "password": "string (required)"
}
```

**Response (200 OK):**
```json
{
    "token": "string (JWT token)",
    "user": {
        "id": "integer",
        "username": "string",
        "email": "string",
        "roles": ["array of roles"]
    }
}
```

**Error Responses:**
- `400 Bad Request`: Missing required fields
- `401 Unauthorized`: Invalid credentials

---

## Item Management Endpoints

### 3. Upload File
**Endpoint:** `POST /api/items/upload`
**Authentication:** Required (JWT token)
**Description:** Upload a file to the cloud storage

**Request Format:** `multipart/form-data`

**Request Payload:**
```
location: string (required) - Location/path for the file
content: file (required) - File to upload
```

**Supported File Types:**
- Text files (`text/plain`)
- Images (`image/jpeg`, `image/png`, `image/gif`, `image/webp`)

**Response (201 Created):**
```json
{
    "id": "integer",
    "location": "string",
    "filename": "string",
    "contentType": "string",
    "createdAt": "string (Y-m-d H:i:s format)"
}
```

**Error Responses:**
- `400 Bad Request`: Missing location or file, invalid file type
- `401 Unauthorized`: User not authenticated
- `500 Internal Server Error`: File save failure

---

### 4. Download File
**Endpoint:** `GET /api/items/{id}/file`
**Authentication:** Required (JWT token)
**Description:** Download a specific file by ID

**Path Parameters:**
- `id`: integer (required) - Item ID

**Response (200 OK):**
- Binary file content with appropriate Content-Type header
- Content-Disposition: inline with filename

**Error Responses:**
- `401 Unauthorized`: User not authenticated
- `403 Forbidden`: Access denied (file belongs to different user)
- `404 Not Found`: File not found

---

### 5. List User's Items
**Endpoint:** `GET /api/items/my`
**Authentication:** Required (JWT token)
**Description:** Get all items belonging to the authenticated user

**Response (200 OK):**
```json
[
    {
        "id": "integer",
        "location": "string",
        "filename": "string",
        "contentType": "string",
        "createdAt": "string (Y-m-d H:i:s format)"
    }
]
```

**Error Responses:**
- `401 Unauthorized`: User not authenticated

---

## Standard CRUD Endpoints (API Platform)

### 6. List All Items
**Endpoint:** `GET /api/items`
**Authentication:** Required (JWT token)
**Description:** Get all items (standard API Platform endpoint)

**Response (200 OK):**
```json
{
    "@context": "string",
    "@id": "string",
    "@type": "string",
    "hydra:member": [
        {
            "@id": "string",
            "@type": "string",
            "id": "integer",
            "user": "string (IRI)",
            "location": "string",
            "filename": "string",
            "contentType": "string",
            "createdAt": "string"
        }
    ],
    "hydra:totalItems": "integer"
}
```

---

### 7. Get Single Item
**Endpoint:** `GET /api/items/{id}`
**Authentication:** Required (JWT token)
**Description:** Get a specific item by ID

**Path Parameters:**
- `id`: integer (required) - Item ID

**Response (200 OK):**
```json
{
    "@context": "string",
    "@id": "string",
    "@type": "string",
    "id": "integer",
    "user": "string (IRI)",
    "location": "string",
    "filename": "string",
    "contentType": "string",
    "createdAt": "string"
}
```

---

### 8. Create Item
**Endpoint:** `POST /api/items`
**Authentication:** Required (JWT token)
**Description:** Create a new item (standard API Platform endpoint)

**Request Payload:**
```json
{
    "location": "string (required)"
}
```

**Response (201 Created):**
```json
{
    "@context": "string",
    "@id": "string",
    "@type": "string",
    "id": "integer",
    "user": "string (IRI)",
    "location": "string",
    "filename": "string",
    "contentType": "string",
    "createdAt": "string"
}
```

---

### 9. Update Item
**Endpoint:** `PUT /api/items/{id}`
**Authentication:** Required (JWT token)
**Description:** Update an existing item

**Path Parameters:**
- `id`: integer (required) - Item ID

**Request Payload:**
```json
{
    "location": "string (required)"
}
```

**Response (200 OK):**
```json
{
    "@context": "string",
    "@id": "string",
    "@type": "string",
    "id": "integer",
    "user": "string (IRI)",
    "location": "string",
    "filename": "string",
    "contentType": "string",
    "createdAt": "string"
}
```

---

### 10. Delete Item
**Endpoint:** `DELETE /api/items/{id}`
**Authentication:** Required (JWT token)
**Description:** Delete an item

**Path Parameters:**
- `id`: integer (required) - Item ID

**Response (204 No Content):**
- Empty response body

---

## Error Response Format

All error responses follow this general format:

```json
{
    "error": "string (error message)",
    "details": ["array of detailed error messages"] // optional
}
```

## HTTP Status Codes

- `200 OK`: Request successful
- `201 Created`: Resource created successfully
- `204 No Content`: Request successful, no content to return
- `400 Bad Request`: Invalid request data
- `401 Unauthorized`: Authentication required or failed
- `403 Forbidden`: Access denied
- `404 Not Found`: Resource not found
- `409 Conflict`: Resource conflict (e.g., duplicate username)
- `500 Internal Server Error`: Server error

## Notes

1. **File Storage**: Files are stored in `/var/www/upload/{username}/` directory
2. **Security**: Users can only access their own files
3. **File Types**: Only text and image files are supported
4. **JWT Tokens**: Tokens should be included in the Authorization header as `Bearer <token>`
5. **API Platform**: Standard CRUD operations are handled by API Platform with HAL+JSON format