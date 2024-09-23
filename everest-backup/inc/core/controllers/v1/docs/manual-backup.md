# Manual Backup API Documentation

This documentation provides detailed information about the Manual Backup API in the Everest Backup system. The Manual Backup API allows you to create a backup of your WordPress site manually.

## API Endpoint

```
POST /everest-backup/v1/manual-backup
```

## Request Parameters

The Manual Backup API expects the following parameters in the request:

- **access_token** (string, required): The access token hash generated using the provided "action".
- **ignore_database** (string, optional): Specifies whether to ignore the database during backup. Default value: "yes". Possible values: "yes" or "no".
- **ignore_plugins** (string, optional): Specifies whether to ignore plugins during backup. Default value: "yes". Possible values: "yes" or "no".
- **ignore_themes** (string, optional): Specifies whether to ignore themes during backup. Default value: "yes". Possible values: "yes" or "no".
- **ignore_media** (string, optional): Specifies whether to ignore media files during backup. Default value: "yes". Possible values: "yes" or "no".
- **ignore_content** (string, optional): Specifies whether to ignore other files/folders in the wp-content folder during backup. Default value: "yes". Possible values: "yes" or "no".
- **custom_name_tag** (string, optional): Provides a custom name for the generated backup files.

## Response

The API response, upon successful completion of the backup process, will include the following properties:

- **status** (string): Indicates the status of the backup process. Always returns "done" on success.
- **progress** (integer): The progress of the Everest Backup process in percentage. Always returns 100 on success.
  - **data** (object): Additional data related to the backup process.
    - **logs** (array): Everest Backup process logs data.
    - **result** (object): The result of the backup process.
      - **zipurl** (string): Direct URL to the generated zip file.
      - **migration_url** (string): Direct URL to the Everest Backup migration key generation admin page.

### Example

#### Request

```http
POST /everest-backup/v1/manual-backup HTTP/1.1
Host: your-wordpress-site
Content-Type: application/json
Authorization: <Basic_Authentication_Using_Application_Password>

{
  "access_token": "9477433fed:c47d72a6b209e3709be25dd194bc8811",
  "ignore_database": "no",
  "ignore_plugins": "no",
  "ignore_themes": "no",
  "ignore_media": "yes",
  "ignore_content": "no",
  "custom_name_tag": "Custom Name"
}
```

#### Response

```http
HTTP/1.1 200 OK
Content-Type: application/json

{
  "status": "done",
  "progress": 100,
  "data": {
    "logs": [
      {
        "init": "backup"
      },
      {
        "type": "info",
        "message": "Backup started"
      },
      {
        "type": "info",
        "message": "Creating config file"
      },
      {
        "type": "info",
        "message": "Config file created"
      },
      {
        "type": "info",
        "message": "Listing database tables."
      },
      {
        "type": "info",
        "message": "Total 12 database tables listed."
      },
      {
        "type": "info",
        "message": "Exporting database"
      },
      {
        "type": "info",
        "message": "Database tables exported."
      },
      {
        "type": "info",
        "message": "Listing plugin files"
      },
      {
        "type": "info",
        "message": "Plugins listed. Total files: 1 [ 28.00 B ]"
      },
      {
        "type": "warning",
        "message": "Media ignored."
      },
      {
        "type": "info",
        "message": "Listing theme files"
      },
      {
        "type": "info",
        "message": "Themes listed. Total files: 372 [ 12.35 MB ]"
      },
      {
        "type": "info",
        "message": "Listing content files"
      },
      {
        "type": "info",
        "message": "Contents listed. Total files: 1 [ 28.00 B ]"
      },
      {
        "type": "info",
        "message": "Wrapping things up"
      },
      {
        "type": "info",
        "message": "Checking available space"
      },
      {
        "type": "info",
        "message": "Space available, archiving files"
      },
      {
        "type": "info",
        "message": "Time elapsed: 10 seconds"
      },
      {
        "type": "info",
        "message": "File size: 10.22 MB"
      },
      {
        "type": "done",
        "message": "Backup completed"
      }
    ],
    "result": {
      "zipurl": "https://your-wordpress-site/wp-content/ebwp-backups/ebwp-custom-name-1685967102-647dd0feca984.ebwp",
      "migration_url": "https://your-wordpress-site/wp-admin/admin.php?page=everest-backup-migration_clone&tab=migration&file=ebwp-custom-name-1685967102-647dd0feca984.ebwp&ebwp_migration_nonce=6577e1f409"
    }
  },
  "hash": "2f2915238adab987321a0b6072b140dd",
  "detail": "[12:11:52] ~ Waiting for response"
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
  "access_token": "9477433fed:c47d72a6b209e3709be25dd194bc8811",
  "ignore_database": "no",
  "ignore_plugins": "no",
  "ignore_themes": "no",
  "ignore_media": "yes",
  "ignore_content": "no",
  "custom_name_tag": "Custom Name"
});

const response = await fetch("<Your_WordPress_Website_URL>/wp-json/everest-backup/v1/manual-backup", {
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
    "access_token": "9477433fed:c47d72a6b209e3709be25dd194bc8811",
    "ignore_database": "no",
    "ignore_plugins": "no",
    "ignore_themes": "no",
    "ignore_media": "yes",
    "ignore_content": "no",
    "custom_name_tag": "Custom Name"
  }' \
  https://your-wordpress-site/wp-json/everest-backup/v1/manual-backup
```

Please note that you need to replace `<Basic_Authentication_Using_Application_Password>` with the actual Basic Authentication using Application Password and `<Your_WordPress_Website_URL>` with the URL of your WordPress website.