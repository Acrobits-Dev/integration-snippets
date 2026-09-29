# Acrobits provisioning and contacts examples

This repository contains a minimal PHP example of Acrobits external provisioning and Web Service Contacts. It is intended as a starting point, not as a complete production service.

## Requirements

- PHP 7.4 or newer.
- A web server with PHP support, such as Apache or Nginx with PHP-FPM.
- HTTPS enabled.
- Read access from the PHP process to `/srv/data/provisioning/`.

HTTPS is required because both endpoints receive user credentials. The examples use POST so credentials do not appear in request URLs or ordinary access logs.

## Install the files

Place the PHP files in the same public web directory:

```text
/var/www/html/provisioning/
|-- helpers.php
|-- acrobits_prov.php
`-- acrobits_contacts.php
```

Store the data outside the public web root:

```text
/srv/data/provisioning/
|-- users/
|   `-- SAMPLE.csv
`-- extProv/
    `-- SAMPLE.xml
```

The filename must match the Cloud ID. The scripts convert the ID to uppercase and treat a trailing `*` (the editable app version) like the live version. Only letters, digits, `_`, and `-` are accepted in Cloud IDs.

For local testing, set `ACROBITS_PROVISIONING_DATA_DIR` to use another data directory.

## User CSV

Copy `example-data/users/SAMPLE.csv` to `/srv/data/provisioning/users/SAMPLE.csv`. Its columns are:

```csv
cloud_username,cloud_password,username,password,display_name,first_name,last_name,avatar,phone_number1
```

- `cloud_username` and `cloud_password` authenticate provisioning and contacts requests.
- `username` and `password` are the SIP credentials returned in Account XML.
- `phone_number1` through `phone_number5` may contain either a number or `label:number`, such as `Mobile:+12025550101`.

The sample `user001` password is plain text so the commands below work immediately. Production passwords should use `bcrypt:<hash>`. Generate a hash with:

```bash
php -r 'echo "bcrypt:", password_hash("replace-with-a-strong-password", PASSWORD_BCRYPT), PHP_EOL;'
```

Replace every sample password and SIP credential before deployment.

## Account XML

Copy `example-data/extProv/SAMPLE.xml` to `/srv/data/provisioning/extProv/SAMPLE.xml` and replace `customer-domain.example` with the deployment hostname.

Placeholders such as `{username}`, `{password}`, and `{display_name}` are filled from the authenticated CSV row. `{cloud_password}` receives the password submitted by the app, not the stored CSV value, so a bcrypt hash is never returned to the client.

The sample XML also configures Web Service Contacts to send the Cloud ID and activation credentials in a form-encoded POST request.

## Cloud Softphone configuration

Configure initial external provisioning as follows:

```text
InitialProvisioningUrl      = https://customer-domain.example/provisioning/acrobits_prov.php
InitialProvisioningMethod   = POST
InitialProvisioningPostData = cloud_id=%fullcode%&cloud_username=%username%&cloud_password=%password%
```

The ampersands must be XML-escaped as `&amp;` when these settings are written inside XML.

## Test

Test provisioning:

```bash
curl --fail-with-body \
  --data-urlencode 'cloud_id=SAMPLE' \
  --data-urlencode 'cloud_username=user001' \
  --data-urlencode 'cloud_password=SAMPLE_PASSWORD' \
  https://customer-domain.example/provisioning/acrobits_prov.php
```

Test contacts:

```bash
curl --fail-with-body \
  --data-urlencode 'cloud_id=SAMPLE' \
  --data-urlencode 'cloud_username=user001' \
  --data-urlencode 'cloud_password=SAMPLE_PASSWORD' \
  https://customer-domain.example/provisioning/acrobits_contacts.php
```

Successful provisioning returns Account XML. Successful contacts requests return a JSON object containing a `contacts` array. Missing parameters return `400`, rejected credentials return `403`, unsupported methods return `405`, and unavailable server data returns `500`.

## Production notes

- Keep `/srv/data/provisioning/` outside the web root and restrict it to the service account that needs it.
- Use HTTPS and bcrypt activation-password hashes.
- Do not enable wildcard CORS unless browser clients genuinely require it and its exposure is acceptable.
- Add rate limiting and monitoring at the web-server or application layer.
- For larger directories, implement `Last-Modified` and `304 Not Modified`; clients refresh contacts frequently.
- Replace the CSV backend with a suitable authenticated data store when the deployment requires concurrent administration or substantial scale.
