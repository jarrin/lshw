# lshw

## Bulk XML import (500+ files)

You can now upload a ZIP file containing many XML files (default up to 650) in one go using the web UI. The uploader will also accept multiple individual `.xml` files, but PHP's `max_file_uploads` may limit that approach — uploading a single ZIP avoids that limit.

Notes:
- Drop a `.zip` file on the upload area or select it using the file picker.
- The server will extract the ZIP and process XML files found inside (default up to 650 entries). The limit is configurable in `config.php` via `IMPORT_ZIP_LIMIT`.
- Long imports may take time; the server increases `set_time_limit(0)` and raises memory limit for this operation.
- If a single XML fails to import it will be logged and the batch will continue.

If you want a different limit or behavior (e.g. process more than 500 files), tell me and I can adjust the code/configuration.
