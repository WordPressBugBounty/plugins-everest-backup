# Access Token API Documentation

This document provides detailed information about the Access Token API in the Everest Backup system. The Access Token API allows you to generate an access token by providing an action string.

## API Endpoint

```
POST /everest-backup/v1/access-token
```

## Request Parameters

The Access Token API expects the following parameters in the request:

- **action** (string): The action string used to generate the access token.

## Response

The API response, upon successful generation of the access token, will include the following properties:

- **action** (string): The action string provided in the request parameter.
- **access_token** (string): The access token hash generated using the provided action.

### Example

#### Request

```http
POST /everest-backup/v1/access-token HTTP/1.1
Host: your-wordpress-site
Content-Type: application/json
Authorization: <Basic_Authentication_Using_Application_Password>

{
  "action": "Test action"
}
```

#### Response

```http
HTTP/1.1 200 OK
Content-Type: application/json

{
  "action": "ebwp-test-action",
  "access_token": "9477433fed:c47d72a6b209e3709be25dd194bc8811"
}
```

## Code Examples

### JavaScript

```javascript
/**
 * @see https://make.wordpress.org/core/2020/11/05/application-passwords-integration-guide/
 */
const headersList = {
  "Accept": "application/json",
  "Authorization": "<Basic_Authentication_Using_Application_Password>"
};

const bodyContent = JSON.stringify({
  "action": "Test action"
});

const response = await fetch("https://your-wordpress-site/wp-json/everest-backup/v1/access-token", {
  method: "POST",
  body: bodyContent,
  headers: headersList
});

const data = await response.json();
console.log(data);
```

### cURL

```bash
curl -X POST \
  -H "Content-Type: application/json" \
  -H "Authorization: <Basic_Authentication_Using_Application_Password>" \
  -d '{
    "action": "Test action"
  }' \
  https://your-wordpress-site/wp-json/everest-backup/v1/access-token
```

Make sure to replace `<Basic_Authentication_Using_Application_Password>` with the actual basic authentication using an application password. Also, adjust the endpoint URL (`https://your-wordpress-site/wp-json/everest-backup/v1/access-token`) according to your WordPress site.

Please refer to the provided code examples and adjust them according to your specific requirements and programming language.