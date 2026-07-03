This repository provides a very simple example of the Acrobits provisioning service and Acrobits contacts service.

## Server Requirements

The server needs a web server with PHP support, for example Apache or Nginx with PHP-FPM.

Requirements:

```text
PHP 7.4 or newer
HTTPS enabled
Web server read access to /srv/data/provisioning/
```

HTTPS is required because the provisioning endpoint receives credentials in the request.

## Web Files

Place the PHP files together in the same public web directory, for example:

```text
/var/www/html/provisioning/
├── helpers.php
├── acrobits_prov.php
└── acrobits_contacts.php
```

## Data Files

The script uses the `/srv/data/provisioning` directory for user data. This directory should be outside the web root directory so that the user data cannot be downloaded. The PHP process also needs read access to the user data.

Place your data files into the data directory as shown in the example below. The filename (`SAMPLE` in the example) must match the `cloud_id`. Use capital letters for the file name.

```text
/srv/data/provisioning/
├── users/
│   └── SAMPLE.csv
└── extProv/
    └── SAMPLE.xml
```

There are example data files provided in the `example-data` directory. Use those as a base for your data files.

## CSV User File

Put the user CSV file here:

```text
/srv/data/provisioning/users/SAMPLE.csv
```

The CSV should follow the format from the provided `example-data`, including columns such as:

```csv
cloud_username,cloud_password,username,password,display_name,first_name,last_name,avatar,phone_number1
```

The provisioning endpoint looks up users by `cloud_username`.

The `cloud_password` value may be stored as a bcrypt hash using this format:

```text
bcrypt:<hash>
```

Plain text `cloud_password` values are also supported, but bcrypt is recommended.

The sample `user001` row uses `SAMPLE_PASSWORD` so the quick test below can work immediately after the example data is copied into place. Replace sample passwords with customer-specific values before production use.

## XML Provisioning Template

Put the XML provisioning template here:

```text
/srv/data/provisioning/extProv/SAMPLE.xml
```

The XML file may contain placeholders that match CSV column names.

Any column from the CSV can be returned in the external provisioning response. To return a value, add the required XML node and use the CSV column name as a placeholder in the format `{column_name}`.

The `cloud_password` column is used to validate the incoming request. After validation succeeds, `{cloud_password}` in the XML response is replaced with the submitted password, not the stored CSV value. This avoids returning a stored bcrypt hash in the provisioning response.

For example, if the CSV contains these columns:

```csv
cloud_username,cloud_password,username,password,display_name
```

then the XML template can use any of those columns:

```xml
<account>
    <title>{display_name}</title>
    <acrobitsDisplayName>{display_name}</acrobitsDisplayName>
    <cloud_username>{cloud_username}</cloud_username>
    <cloud_password>{cloud_password}</cloud_password>
    <username>{username}</username>
    <password>{password}</password>
</account>
```

When provisioning is requested, placeholders such as `{username}`, `{password}`, and `{display_name}` are replaced with values from the matching CSV row.

## Endpoint URLs

Provisioning endpoint:

```text
https://customer-domain.example/provisioning/acrobits_prov.php?cloud_id=SAMPLE&cloud_username=user001&cloud_password=SAMPLE_PASSWORD
```

Contacts endpoint:

```text
https://customer-domain.example/provisioning/acrobits_contacts.php?cloud_id=SAMPLE&cloud_username=user001
```

Replace `customer-domain.example`, `SAMPLE`, `user001`, and `SAMPLE_PASSWORD` with the customer's real values.

## Quick Test

Test provisioning:

```bash
curl "https://customer-domain.example/provisioning/acrobits_prov.php?cloud_id=SAMPLE&cloud_username=user001&cloud_password=SAMPLE_PASSWORD"
```

Expected result: XML provisioning output.

Test contacts:

```bash
curl "https://customer-domain.example/provisioning/acrobits_contacts.php?cloud_id=SAMPLE&cloud_username=user001"
```

Expected result: JSON contacts output.

## Security Notes

Keep `/srv/data/provisioning/` outside the public web directory. The CSV file may contain passwords and must not be directly downloadable.

Make sure only the web server process can read the provisioning data files.

Use HTTPS for all provisioning and contacts requests.
