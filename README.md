# Attendance Data Organization System

## Quick Start Guide

This solution processes your master attendance Excel file and automatically creates separate files for each department.

### What This Does

1. ✓ Reads the master Excel file: `Master File Bi Chain Mamatid 2026 (1).xlsx`
2. ✓ Identifies all unique departments in the data
3. ✓ Creates a new folder for each department in `c:\xampp\htdocs\ATTENDANCE\`
4. ✓ Generates separate Excel files for each department with:
   - All original column headers
   - Only records belonging to that department
   - Properly formatted columns
   - Ready-to-use format

### Files Provided

```
c:\xampp\htdocs\ATTENDANCE\
├── process_departments.py              [MAIN SCRIPT - Python]
├── check_excel_structure.py            [Verification script]
├── run_process_departments.bat         [Batch file to run main script]
├── check_structure.bat                 [Batch file to verify structure]
├── process_departments_web.php         [Web interface (optional)]
├── PROCESSING_INSTRUCTIONS.md          [Detailed instructions]
└── README.md                           [This file]
```

## Quick Run Instructions

### Method 1: Command Prompt (Easiest)

```bash
# Open Command Prompt and run:
cd c:\xampp\htdocs\ATTENDANCE

# First, verify the file structure:
python check_excel_structure.py

# Then run the processing:
python process_departments.py
```

### Method 2: Using Batch Files

1. Open File Explorer
2. Navigate to: `c:\xampp\htdocs\ATTENDANCE`
3. Double-click: `run_process_departments.bat`
4. Watch the output and let it complete

### Method 3: Web Interface

Open in your browser: `http://localhost/ATTENDANCE/process_departments_web.php`

## Requirements

### Python (Required)
- Python 3.x must be installed
- Available from: https://www.python.org/downloads/

### During Python Installation
✓ Check: "Add Python to PATH"
✓ Choose: Install for all users

### Python Library (Required)
Run this command once:
```
pip install openpyxl
```

## Installation Steps

### Step 1: Install Python (if not already installed)

1. Go to https://www.python.org/downloads/
2. Download Python 3.12 (or latest 3.x version)
3. Run the installer
4. **IMPORTANT:** Check the box "Add Python to PATH"
5. Click "Install Now"
6. Wait for completion

### Step 2: Install openpyxl Library

1. Open Command Prompt
2. Run: `pip install openpyxl`
3. Wait for completion (should say "Successfully installed")

### Step 3: Verify Installation

1. Open Command Prompt
2. Run: `python --version` (should show version like "Python 3.12.0")
3. Run: `python -c "import openpyxl; print('openpyxl OK')"` (should print "openpyxl OK")

## Running the Scripts

### Verification First (Recommended)

```
python check_excel_structure.py
```

**Output shows:**
- ✓ File location confirmed
- ✓ Number of rows and columns
- ✓ Column headers
- ✓ Department column identified
- ✓ List of all departments with record counts
- ✓ Sample data preview

### Then Run Processing

```
python process_departments.py
```

**Output shows:**
- ✓ File loaded
- ✓ Headers extracted
- ✓ Department column found
- ✓ Creating folders...
- ✓ Creating Excel files...
- ✓ Final summary with statistics

## Expected Results

After running `process_departments.py`, you will have:

### New Folder Structure
```
c:\xampp\htdocs\ATTENDANCE\
├── Department Name 1\
│   └── Department Name 1_attendance.xlsx
├── Department Name 2\
│   └── Department Name 2_attendance.xlsx
├── Department Name 3\
│   └── Department Name 3_attendance.xlsx
... (one folder per department)
```

### Each Excel File Contains:
- ✓ Same headers as the master file
- ✓ All records for that department
- ✓ Properly formatted columns
- ✓ Ready to use or share

## Example Output

```
================================================================================
ATTENDANCE DATA PROCESSING SCRIPT
================================================================================

✓ File found: c:\xampp\htdocs\ATTENDANCE\coordinator\Master File Bi Chain Mamatid 2026 (1).xlsx
✓ Workbook loaded successfully
  Sheet name: Sheet1
  Total rows: 1256
  Total columns: 15

✓ Headers extracted (15 columns):
    1. Employee ID
    2. Name
    3. Department
    4. Date
    ... etc ...

✓ Department column found: Column 3 (Department)

✓ Departments found (8):
    • Admin: 145 records
    • Finance: 234 records
    • HR: 89 records
    • IT: 312 records
    • Marketing: 156 records
    • Operations: 198 records
    • Sales: 267 records
    • Support: 145 records

================================================================================
CREATING DEPARTMENT FOLDERS AND FILES
================================================================================

✓ Created folder: Admin
  ✓ Created file: Admin_attendance.xlsx (145 records)

✓ Created folder: Finance
  ✓ Created file: Finance_attendance.xlsx (234 records)

... [continuing for each department] ...

================================================================================
SUMMARY
================================================================================

✓ Total departments processed: 8/8
✓ Total records distributed: 1548

✓ Department Summary:
    Admin: 145 records - Folder: Created
    Finance: 234 records - Folder: Created
    ... etc ...

================================================================================
✓ PROCESSING COMPLETE!
================================================================================
```

## Troubleshooting

### Problem: "Python is not found"
**Solution:**
1. Ensure Python is installed from python.org
2. During installation, check "Add Python to PATH"
3. Restart Command Prompt after installation
4. Test with: `python --version`

### Problem: "ModuleNotFoundError: No module named 'openpyxl'"
**Solution:**
```
pip install openpyxl
```

### Problem: "File not found error"
**Solution:**
1. Verify file exists: `c:\xampp\htdocs\ATTENDANCE\coordinator\Master File Bi Chain Mamatid 2026 (1).xlsx`
2. Check for exact filename match (capitalization and spaces matter)
3. Ensure no Excel files are open

### Problem: "Permission denied"
**Solution:**
1. Close any open Excel files
2. Run Command Prompt as Administrator
3. Ensure you have write permissions on the ATTENDANCE folder

### Problem: "Department column not found"
**Solution:**
1. Run `check_excel_structure.py` to identify columns
2. Verify the department column name
3. If different from expected, contact support with the output

### Problem: "Access denied" when creating folders/files
**Solution:**
1. Close all Excel files
2. Ensure folder is not read-only
3. Run Command Prompt as Administrator
4. Check antivirus isn't blocking file creation

## Advanced Usage

### Modify Column Detection
If the department column has a different name, edit `process_departments.py`:

Find this section:
```python
for idx, header in enumerate(headers, 1):
    if header and ('dept' in str(header).lower() or 'department' in str(header).lower()):
```

Change the condition to match your column name.

### Use Different Output Location
Edit this line in `process_departments.py`:
```python
base_dir = r'c:\xampp\htdocs\ATTENDANCE'
```

Change to your desired location.

### Process Only Specific Sheets
Edit this line in `process_departments.py`:
```python
ws = wb.active
```

Change to:
```python
ws = wb['Sheet Name']
```

## FAQ

**Q: What if the Excel file is corrupted?**
A: The script will show an error. Try opening the file in Excel and saving it as a new file.

**Q: Can I run this on multiple files?**
A: Yes, copy the script and modify the `file_path` variable to point to different files.

**Q: Will existing department folders be overwritten?**
A: Yes, existing files will be overwritten. Make backups of any important data.

**Q: How long does it take?**
A: Usually under 1 minute for typical Excel files with 1000+ rows.

**Q: Can I undo the changes?**
A: The original master file is never modified. You can delete created folders to start over.

**Q: What if a department name has special characters?**
A: Folder names with special characters (< > : " | ? * \) will be handled automatically.

## Support

For issues:

1. **Check Troubleshooting** section above
2. **Run verification**: `python check_excel_structure.py`
3. **Review the output** carefully for clues
4. **Ensure requirements** are met (Python installed, openpyxl installed)
5. **Try Administrator mode** (right-click Command Prompt → Run as Administrator)

## More Information

- See: `PROCESSING_INSTRUCTIONS.md` for detailed step-by-step guide
- Check: `check_excel_structure.py` to understand file structure
- Review: `process_departments.py` source code for customization

---

**Version:** 1.0
**Last Updated:** 2024
**Purpose:** Automate attendance data organization by department
**Status:** Ready to use
