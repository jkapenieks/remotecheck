# Assignment submission plugin: Remote Check

This Moodle assignment submission subplugin lets students choose a **Building Address** from a **remote MySQL table**, enter **up to 9 parameters**, and provide a **calculation result** derived from a **configurable formula**. The plugin validates:

1. Each parameter against the remote dataset with configurable **absolute/percentage tolerance**.
2. The student’s calculation against the **formula** (and optionally also against the remote table’s _Calculation Result_ column).

> Component: `assignsubmission_remotecheck`  
> Path: `moodle/mod/assign/submission/remotecheck`

## Installation
1. Place this folder at `mod/assign/submission/remotecheck`.
2. Visit **Site administration → Notifications** to let Moodle install database tables.
3. Configure **Site administration → Plugins → Activity modules → Assignment → Submission plugins → Remote Check**:
   - Remote DB connection (host, port, db, user, password – use a **read-only** user).
   - Remote table name and columns mapping (ID, Address, Param1..Param9, Calculation Result).
4. When creating/editing an assignment, enable **Remote Check** in the submission plugins section and specify:
   - **Formula** (use variables `p1..p9`, e.g., `(p1+p2)/p3`).
   - **Absolute tolerance** and **Percentage tolerance**.
   - Optional: also compare against the remote dataset’s **Calculation Result** column.

## Usage (student)
- Select the **Building Address** from a dropdown (loaded from the remote table).
- Enter values for **Param 1..9** and the **Result** according to the assignment instructions.
- Submit. The plugin stores inputs and runs validation.

## Validation details
- For each parameter `pi`, the plugin calculates `abs(student - expected) <= max(absTol, |expected| * pctTol/100)`.
- The **Result** is validated against the configured **Formula** evaluated with `p1..p9`.
- If enabled, the Result is also compared to the remote **Calculation Result** field using the same tolerance rule.
- Outcomes are stored in JSON in `assignsubmission_remotecheck.validationjson` and shown in the submission view.

## Security & reliability
- Remote DB credentials are stored in plugin config. Use a **read-only** DB user restricted to the required table.
- Formula evaluation allows only numbers, operators `+ - * / ^`, parentheses, and variables `p1..p9`; the expression is sanitized before evaluation.
- For performance, you can add a cache store for the definition `remote_rows`; TTL is configurable.

## Notes
- This sample keeps the UI minimal and focuses on core requirements. You can extend it to block submission on validation failure, add AJAX address loading, or auto-calculate the result on the client.
- Tested minimally with Moodle 4.0+ code patterns; adjust namespaces or APIs to match your exact Moodle version.
