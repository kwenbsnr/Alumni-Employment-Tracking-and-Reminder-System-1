```
📁 Annotated Directory Structure

├─ admin/                                 # Admin module
│  ├─ admin_dashboard.php          # Admin dashboard
│  ├─ alumni_management.php        # Displays only the batch cards
│  ├─ edit_alumni.php              # Future dev; allows admin to edit alumni details
│  ├─ get_documents.php            # For admin document viewing
│  ├─ update_alumni.php            # Admin backend logic
│  ├─ update_status.php            # Handles approval/rejection of submissions
│  ├─ admin_format.php             # Admin header and sidebar layout
│  ├─ batch_alumni.php             # Shows batch cards and details with search/filter features
│  ├─ get_alumni_details.php       # For hover functionality
│  ├─ activity_log.php             # Tracks admin actions and activity history
│  ├─ check_paths.php              # Temp; debugging utility file
│  └─ admin_format.css             # Admin header and sidebar styles
│
├─ alumni/                               # Alumni module
│  ├─ alumni_dashboard.php        # Alumni dashboard
│  ├─ alumni_format.php           # Alumni header and sidebar layout
│  ├─ alumni_profile.php          # Profile management page
│  ├─ update_profile.php          # Alumni backend logic
│  └─ alumni_format.css           # Alumni header and sidebar styles
```


**Alumni Module Bug Report**


🔴 Critical
____________________

1. Start year vs. graduation year logic:
If a user is a "Student" or “Employed & Student,” check that start year is later than graduation year. Additionally, the graduation year must be later than the start year.

2. Submission clearing issue:
When a rejected profile is resubmitted, previously entered details appear in the form, but clicking submit clears all data and reopens the form incorrectly. The form should reset automatically and allow smooth resubmission.

🟠 High Priority
______________________

1. "Employed & Student" submission issue:
If a user selects "Employed & Student" in the employment status, the form submits successfully but does not store data in the employment_status column of the alumni_profile table and does not add a row in the alumni_documents table. Additionally, no data is displayed in the dashboard cards.

3. Yellow rejection card display:
Rejection cards must appear in the dashboard, not only in the proceeding tab. It should match the style of the “Complete Your Profile” card.

🟡 Medium Priority 
______________________

1. Alumni data display:
After a successful submission, the Employment/Academic Details cards must be displayed on the dashboard using the same UI style as existing cards, positioned below the existing cards for consistency. Currently, these cards are displayed only in the Profile Management tab.

🟢 Low Priority 
______________________
[DONE]
1. Successful submission display issue:
Successful submission must appear on the dashboard like the “Complete Your Profile” card, but the color should be green.

2. Start & end year display:
Values must display correctly in the Employment/Academic Details card after successful submission.

3. Apostrophe handling:
Employment/Academic Details display cards after successful submission have issues with apostrophe rendering.

4. Header bar scroll issue: The header bar must remain fixed and not be scrollable.


ALUMNI SUGGESTIONS FEATURES

* Include a **“Data Completeness Indicator”** such as a progress bar (e.g., “Profile 75% Complete”) to guide users in filling out missing information and ensure more complete records.

* Integrate an **address verification tool** (e.g., Google Maps API) to enable auto-filling and confirmation of addresses, minimizing typos and ensuring accurate geographic data. (ONLY IF NAAY EXTRA TIME)

* Use **pre-populated forms** to automatically fill fields like name, student ID, and graduation year based on existing records, so alumni only need to confirm rather than re-enter their data.

* Add **“Help” or “Info” icons** beside complex fields (e.g., “Employment Status”) to display brief explanations or tooltips when hovered over, ensuring users understand what information is required and improving data accuracy.
______________________
______________________
______________________

**Admin Module Bug Report**

🟢 Low Priority 
______________________

1. Sidebar and Header Bar Improvements: Currently, both have a fixed height and are scrollable.

2. Recent Activity Log Page Refinement.
  
3. General UI Refinement.

http://localhost/Alumni-Employment-Tracking-and-Reminder-System/login/login.php



ALUMNI ISSUES
1. Layout is Cluttered and Unbalanced: The main screen uses too many large boxes, making it look dense. We should arrange the most important items (Profile, Job, Documents) across the top and combine any redundant sections to make the dashboard easier to scan quickly.
2. Profile Status is Confusing: The system says your profile is $100\%$ complete, but then calls it INCOMPLETE. This is contradictory. If it's $100\%$, the status should be changed to COMPLETE, and the button should allow you to view or edit the profile, not complete it.
3. Document Sections are Redundant: There are two separate boxes for documents that essentially repeat the same information (the file count and the "Under Review" status). These two boxes should be combined into one clear Document Management section to save space and reduce confusion.
4. Activity Log is Not an Activity Log: The "Recent Activity" panel just lists static information (like your graduation year) instead of showing what you or the admin actually did. This panel needs to be changed to show a real, chronological list of actions taken, like "Updated employment" or "Admin started review."
5. Design Needs Modernization: The current look is functional but relies on large, blocky cards and heavy colors, making it look a bit old. Updating the design with a lighter feel, softer colors, and more contemporary icons would make the portal more pleasant to use.