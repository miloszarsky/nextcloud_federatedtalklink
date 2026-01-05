# Federated Talk Link

A Nextcloud app that generates federated links to Talk rooms hosted on external Nextcloud servers.

## Features

- Query external Nextcloud Talk servers to find rooms by name
- Generate direct call links for federated access
- Admin settings to configure external server credentials
- Integration with Nextcloud Talk

## Requirements

- Nextcloud 27 - 30
- PHP 8.1+

## Deployment

### Quick Install (No Build Tools Required)

1. **Clone the repository** into your Nextcloud apps directory:
   ```bash
   cd /var/www/nextcloud/apps
   git clone https://github.com/miloszarsky/nextcloud_federatedtalklink.git federatedtalklink
   ```

2. **Set proper ownership**:
   ```bash
   chown -R www-data:www-data /var/www/nextcloud/apps/federatedtalklink
   ```

3. **Enable the app**:
   ```bash
   sudo -u www-data php /var/www/nextcloud/occ app:enable federatedtalklink
   ```

That's it! All built files are included in the repository.

## Configuration

1. Log in as an administrator
2. Go to **Administration Settings** → **Federated Talk Link**
3. Configure the following settings:

| Setting | Description | Example |
|---------|-------------|---------|
| External Server URL | The Nextcloud server to query for rooms | `ext.example.com` |
| Username | Authentication username | `guest` |
| Password | Authentication password | `your-password` |
| Target Nextcloud URL | The URL for generated links | `nextcloud.example.com` |

4. Click **Save Settings**
5. Click **Test Connection** to verify the configuration

## API Usage

### Generate a federated link

```bash
curl -u username:password \
  "https://your-nextcloud.com/ocs/v2.php/apps/federatedtalklink/api/v1/link?roomName=MyRoom" \
  -H "OCS-APIRequest: true" \
  -H "Accept: application/json"
```

**Response:**
```json
{
  "ocs": {
    "data": {
      "link": "https://nextcloud.example.com/call/abc123xyz",
      "roomName": "MyRoom",
      "token": "abc123xyz"
    }
  }
}
```

### Search rooms

```bash
curl -u username:password \
  "https://your-nextcloud.com/ocs/v2.php/apps/federatedtalklink/api/v1/rooms?search=meeting" \
  -H "OCS-APIRequest: true" \
  -H "Accept: application/json"
```

### Test connection

```bash
curl -u username:password \
  "https://your-nextcloud.com/ocs/v2.php/apps/federatedtalklink/api/v1/test" \
  -H "OCS-APIRequest: true" \
  -H "Accept: application/json"
```

## Updating

```bash
cd /var/www/nextcloud/apps/federatedtalklink
git pull
sudo -u www-data php /var/www/nextcloud/occ app:disable federatedtalklink
sudo -u www-data php /var/www/nextcloud/occ app:enable federatedtalklink
```

## Development

For development, you need Node.js 20+ and npm 10+:

```bash
# Install dependencies
npm install --legacy-peer-deps

# Build for development (with source maps)
npm run dev

# Watch mode (auto-rebuild on changes)
npm run watch

# Production build
npm run build
```

## License

AGPL-3.0-or-later
