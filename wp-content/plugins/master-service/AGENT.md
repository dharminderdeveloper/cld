# Master Service Plugin Agent Guide

## Overview

`master-service` is a WordPress plugin for collecting Master Service Log requests through a frontend modal form. It handles client/service details, optional document uploads, affidavit preferences, email delivery through `wp_mail()`, and daily JSON logging.

The plugin is intentionally small:

- Frontend form markup is rendered by a shortcode in `master-service.php`.
- Browser behavior lives in `assets/js/master-service.js`.
- Submission handling lives in `includes/form-handler.php`.
- Daily JSON logging lives in `includes/logger.php`.
- Styling lives in `assets/css/master-service.css`.

## Main Features

- Renders a modal form for Master Service Log submissions.
- Opens the modal from `.home-cta-1 a` and `.btn-yellow` click targets.
- Validates required client fields and agreement checkboxes.
- Manages up to 6 uploaded documents through a custom JavaScript file manager.
- Uploads valid files through WordPress `wp_handle_upload()`.
- Sends an HTML email to `info@calgarylegaldocs.ca` with uploaded files attached.
- Writes a daily JSON log entry to `logs/YYYY-MM-DD.json`.
- Returns JSON responses for the frontend `fetch()` workflow.

## Folder and File Structure

- `master-service.php`
  - Main plugin bootstrap.
  - Defines `MASTER_PLUGIN_DIR`, `MASTER_PLUGIN_URL`, and `MASTER_LOGS_DIR`.
  - Includes `includes/logger.php` and `includes/form-handler.php`.
  - Defines `Master_Service_Plugin_Main`.
  - Registers activation setup, frontend assets, shortcode, and `admin_post` handlers.
- `includes/form-handler.php`
  - Defines `master_handle_form_submission()`.
  - Verifies request method and nonce.
  - Sanitizes and validates submitted fields.
  - Validates and uploads files.
  - Builds and sends the HTML email.
  - Builds the JSON log entry and response.
- `includes/logger.php`
  - Defines `master_append_log()`.
  - Creates/updates daily JSON log files under `MASTER_LOGS_DIR`.
- `assets/js/master-service.js`
  - Defines `showModal()`, `closeModal()`, `FileUploadManager`, loader helpers, field error helpers, and form submission logic.
  - Uses `fetch()` and `FormData` to POST to WordPress `admin-post.php`.
- `assets/css/master-service.css`
  - Styles modal, form sections, buttons, validation messages, loader overlay, and responsive layout.
- `logs/`
  - Created on activation if missing.
  - Stores daily log files as `YYYY-MM-DD.json`.
  - Protected by a generated `.htaccess` file on Apache only.

## Entry Points and Initialization Flow

1. WordPress loads `master-service.php`.
2. Direct access is blocked with `if (! defined('ABSPATH')) exit;`.
3. Constants are defined:
   - `MASTER_PLUGIN_DIR`
   - `MASTER_PLUGIN_URL`
   - `MASTER_LOGS_DIR`
4. `includes/logger.php` and `includes/form-handler.php` are required.
5. `new Master_Service_Plugin_Main()` instantiates the main plugin class.
6. The constructor registers:
   - `register_activation_hook(__FILE__, [$this, 'on_activation'])`
   - `add_action('wp_enqueue_scripts', [$this, 'enqueue_assets'])`
   - `add_shortcode('mastter_service_form', [$this, 'render_form_shortcode'])`
   - `add_action('admin_post_nopriv_master_service_submit', 'master_handle_form_submission')`
   - `add_action('admin_post_master_service_submit', 'master_handle_form_submission')`

Important: the registered shortcode is `[mastter_service_form]` with a double `t` in `mastter`. `README.txt` mentions `[master_service_form]`, which does not match the current code.

## Important PHP Classes, Functions, and Hooks

- `Master_Service_Plugin_Main`
  - Main plugin class in `master-service.php`.
- `Master_Service_Plugin_Main::on_activation()`
  - Creates `MASTER_LOGS_DIR`.
  - Writes `logs/.htaccess` with Apache deny rules.
- `Master_Service_Plugin_Main::enqueue_assets()`
  - Enqueues `master-frontend-css`.
  - Enqueues `master-frontend-js` with `jquery` as a dependency.
  - Localizes `masterServiceConf` with:
    - `post_url`: `admin_url('admin-post.php')`
    - `nonce`: `wp_create_nonce('master_service_nonce')`
- `Master_Service_Plugin_Main::render_form_shortcode()`
  - Outputs the modal and `<form id="masterServiceForm">`.
- `master_handle_form_submission()`
  - Handles both logged-in and anonymous submissions.
  - Sends JSON success/error responses.
- `master_append_log($entry)`
  - Appends submission metadata to `logs/YYYY-MM-DD.json`.

## Shortcode Usage

Current registered shortcode:

```text
[mastter_service_form]
```

Warning: `README.txt` documents `[master_service_form]`, but the code registers `[mastter_service_form]`. Before renaming or adding aliases, check live pages because existing content may rely on the misspelled shortcode.

## JavaScript Functionality

`assets/js/master-service.js` provides:

- `showModal()`
  - Shows `#contactModal`.
  - Disables body scrolling.
- `closeModal()`
  - Hides `#contactModal`.
  - Restores body scrolling.
- `FileUploadManager`
  - Tracks selected files in `selectedFiles`.
  - Enforces client-side limits:
    - 6 files maximum.
    - 6MB per file.
    - 30MB total.
    - Extensions: `.pdf`, `.doc`, `.docx`, `.jpg`, `.jpeg`, `.png`.
  - Updates `#fileListContainer`.
  - Displays errors in `.file-upload-error-msg`.
- `showLoader()` / `hideLoader()`
  - Toggles `#ajaxLoader`.
- `isValidEmailAddress()`
  - Performs client-side email regex validation.
- `setFieldError()` / `clearFieldError()`
  - Adds/removes `.master-field-error`.

On `DOMContentLoaded`, the script:

- Binds `.home-cta-1 a` and `.btn-yellow` clicks to `showModal()`.
- Creates the global `fileManager = new FileUploadManager()`.
- Binds validation cleanup handlers to form fields.
- Intercepts `#masterServiceForm` submission.
- Builds `FormData`.
- Sends the request with `fetch()`.

Although `jquery` is listed as a script dependency, the current JavaScript mostly uses vanilla DOM APIs and `fetch()`.

## Form Submission Workflow

1. User opens the modal through `.home-cta-1 a`, `.btn-yellow`, or direct `showModal()` call.
2. User fills `#masterServiceForm`.
3. Optional files are selected through `#hiddenFileInput`.
4. `FileUploadManager` stores files in memory; the hidden file input has no `name` attribute.
5. On submit, JavaScript validates:
   - `client_name`
   - `email`
   - `terms_acknowledged`
   - `final_terms`
   - selected file limits/types
6. JavaScript builds `FormData(form)`.
7. JavaScript appends:
   - `action=master_service_submit`
   - `_master_service_nonce=masterServiceConf.nonce`
   - each selected file as `document_upload[]`
8. JavaScript posts to `masterServiceConf.post_url`, which is WordPress `admin-post.php`.
9. WordPress routes the request by `action` to `master_handle_form_submission()`.
10. PHP validates, uploads files, sends email, logs the request, and returns JSON.
11. JavaScript shows success/error text and resets the form on success.

## Validation Logic Overview

Client-side validation in `assets/js/master-service.js`:

- Requires non-empty `client_name`.
- Requires non-empty `email`.
- Validates email with `/^[^\s@]+@[^\s@]+\.[^\s@]+$/`.
- Requires checked `terms_acknowledged`.
- Requires checked `final_terms`.
- Enforces upload limits before files are added to `selectedFiles`.

Server-side validation in `includes/form-handler.php`:

- Only accepts `POST`.
- Verifies `_master_service_nonce` against `master_service_nonce`.
- Requires non-empty `client_name`.
- Requires non-empty `email`.
- Validates email with `is_email()`.
- Requires `terms_acknowledged`.
- Requires `final_terms`.
- Enforces upload limits:
  - `max_files`: 6
  - `max_file_size`: `6 * 1024 * 1024`
  - `max_total_size`: `30 * 1024 * 1024`
  - allowed MIME map for `pdf`, `doc`, `docx`, `jpg`, `jpeg`, `png`

Never remove server-side validation because browser validation can be bypassed.

## Database and Logging

- No custom database tables are created.
- No plugin options/settings are stored.
- Uploaded documents are stored by WordPress in the normal uploads location via `wp_handle_upload()`.
- Submission logs are written to plugin files under:

```text
wp-content/plugins/master-service/logs/YYYY-MM-DD.json
```

`master_append_log()` reads the existing JSON array, appends the new entry, writes a `.tmp` file, changes permissions to `0644`, then renames the temp file to the daily log filename.

## Admin Panel Functionality

There is no custom WordPress admin settings page, menu, list table, or dashboard screen in the current plugin.

Configuration is currently code-based:

- Email recipient is hardcoded in `includes/form-handler.php`.
- Upload limits are hardcoded in both PHP and JavaScript.
- Form fields are hardcoded in `Master_Service_Plugin_Main::render_form_shortcode()`.

## External APIs and Services

The plugin uses WordPress core APIs only:

- Shortcodes: `add_shortcode()`
- Hooks: `add_action()`, `register_activation_hook()`
- Assets: `wp_enqueue_style()`, `wp_enqueue_script()`, `wp_localize_script()`
- Endpoint: `admin-post.php`
- Security: `wp_create_nonce()`, `wp_verify_nonce()`
- Uploads: `wp_handle_upload()`
- Email: `wp_mail()`
- JSON responses: `wp_send_json()`, `wp_send_json_success()`, `wp_send_json_error()`
- Logging helpers: `wp_json_encode()`, `current_time()`, `date_i18n()`

## Security Practices

- Direct PHP file access is blocked with `ABSPATH` checks.
- Frontend requests include `_master_service_nonce`.
- Server verifies the nonce with `wp_verify_nonce()`.
- Text fields use `sanitize_text_field()`.
- Textareas use `sanitize_textarea_field()`.
- Emails use `sanitize_email()` and `is_email()`.
- File names use `sanitize_file_name()`.
- Email HTML values are escaped with `esc_html()`.
- Uploaded file URLs are escaped with `esc_url()`.
- Upload extensions and MIME types are restricted.
- Log directory gets an Apache `.htaccess` deny file on activation.

Security caveats:

- `.htaccess` protection only applies on Apache-compatible servers.
- Logs live inside the plugin directory and contain submitted personal/contact information.
- File MIME/type trust depends on WordPress `wp_handle_upload()` and the configured MIME map.

## Example Request Lifecycle

1. A visitor clicks `.btn-yellow`.
2. `showModal()` displays `#contactModal`.
3. The visitor enters:
   - `client_name`
   - `email`
   - optional company/service/recipient fields
   - agreement checkboxes
   - optional files
4. The submit handler validates required fields.
5. `FormData` is posted to `/wp-admin/admin-post.php`.
6. WordPress sees `action=master_service_submit`.
7. `admin_post_nopriv_master_service_submit` routes anonymous users to `master_handle_form_submission()`.
8. PHP verifies nonce and required fields.
9. PHP uploads `document_upload[]` files with `wp_handle_upload()`.
10. PHP sends an HTML email with file attachments to `info@calgarylegaldocs.ca`.
11. PHP appends a daily log entry.
12. PHP returns JSON.
13. JS shows `.success-msg` or `.error-msg`; on success it resets the form and closes the modal after 3 seconds.

## Example AJAX Request

Endpoint:

```text
POST /wp-admin/admin-post.php
```

Multipart fields:

```text
action=master_service_submit
_master_service_nonce=<nonce>
client_name=Jane Doe
company_name=Example Inc.
reference_no=ABC-123
phone=555-123-4567
email=jane@example.com
billing_address=123 Main St
service_deadline=2026-05-20
service_time=10:30
service_priority=Regular Service - $80.00
document_delivery_method=Upload Documents Now
document_name=Statement of Claim
document_filed_date=2026-05-14
affidavit_success_action=file_and_send
affidavit_unsuccessful_action=send_only
recipient_name=John Smith
recipient_phone=555-555-5555
recipient_email=john@example.com
recipient_city=Calgary
recipient_postal=T2P 0A1
recipient_address=456 Court Ave
mail_affidavit=on
terms_acknowledged=on
final_terms=on
document_upload[]=<File>
```

Success response:

```json
{
  "success": true,
  "message": "Form submitted successfully. We will contact you shortly."
}
```

Failure response:

```json
{
  "success": false,
  "message": "Please enter a valid email address."
}
```

Other common failure messages:

- `Invalid request method.`
- `Security check failed (invalid nonce).`
- `Please enter your name.`
- `Please enter your email address.`
- `Please acknowledge the regular service priority provisions.`
- `Please agree with the terms and conditions.`
- `Exceeded max number of files.`
- `One of the files exceeds the maximum allowed file size.`
- `Total upload size exceeds allowed limit.`
- `One of the files has an invalid file type.`
- `Failed to send request. Please try again later.`

## Email Workflow

`includes/form-handler.php` builds:

- `$to = 'info@calgarylegaldocs.ca'`
- `$subject = 'New Master Service Request - ' . $client_name`
- HTML `$message` with client, service, document, recipient, affidavit, and agreement details.
- `$headers`:
  - `From: Master Service <info@calgarylegaldocs.ca>`
  - `Reply-To: <submitted email>`
  - `Content-Type: text/html; charset=UTF-8`
- `$attachments` from uploaded file paths returned by `wp_handle_upload()`.

Warning: email wording, recipient, headers, attachments, and log fields are tightly coupled in `master_handle_form_submission()`. When adding/removing form fields, update sanitization, validation if needed, email output, log fields, and frontend markup together.

## Common Modification Areas

- Add/change form fields:
  - Update `render_form_shortcode()` markup.
  - Update JS validation/submission only if the field needs special behavior.
  - Update `master_handle_form_submission()` sanitization.
  - Update email body.
  - Update log entry.
- Change upload rules:
  - Update `FileUploadManager` limits/types in `assets/js/master-service.js`.
  - Update `$upload_limits` in `includes/form-handler.php`.
  - Update visible helper text in the shortcode markup.
- Change email recipient or sender:
  - Edit `$to` and `$headers` in `includes/form-handler.php`.
- Change modal triggers:
  - Edit `.home-cta-1 a, .btn-yellow` selector in `assets/js/master-service.js`.
- Change styling:
  - Edit `assets/css/master-service.css`.

## Known Limitations and Technical Debt

- Shortcode mismatch: code registers `[mastter_service_form]`, while `README.txt` says `[master_service_form]`.
- No admin settings page; operational settings are hardcoded.
- No custom database storage; logs are JSON files in the plugin directory.
- Logs may fail silently if the PHP process cannot write to the plugin directory.
- Apache `.htaccess` protection does not protect logs on all web servers.
- Hidden file input lacks a `name`; file upload depends entirely on JavaScript appending `document_upload[]`.
- `jquery` is enqueued as a dependency but current code does not meaningfully use jQuery.
- Some frontend HTML is inline inside PHP, making field changes cross-cutting.
- There are no automated tests in this plugin.

## Recommended Development Workflow

- Read this file first, then inspect only the touched code path.
- Keep field names stable unless updating JS, PHP sanitization, email output, and logs together.
- Keep client-side and server-side validation in sync.
- Keep upload limits synchronized across:
  - shortcode helper text
  - `FileUploadManager`
  - `$upload_limits`
- Test both anonymous and logged-in submissions because both `admin_post_nopriv_*` and `admin_post_*` are registered.
- Test with and without file uploads.
- Test invalid nonce, invalid email, missing agreement checkboxes, too many files, oversized files, and invalid file types.
- Verify email attachment behavior after changing upload logic.
- Verify `logs/YYYY-MM-DD.json` after changing log structure.

## Warnings Before Modifying Validation or Email

- Do not rely only on JavaScript validation.
- Do not weaken file validation without understanding upload/security implications.
- Do not change required checkbox names unless server-side checks are updated.
- Do not change `action=master_service_submit` unless both JS and `admin_post` hooks are updated.
- Do not change `_master_service_nonce` or `master_service_nonce` unless both JS localization/submission and PHP verification are updated.
- Do not alter email attachments without verifying `wp_mail()` still receives local file paths, not public URLs.
- Treat `info@calgarylegaldocs.ca` as production-facing unless told otherwise.
