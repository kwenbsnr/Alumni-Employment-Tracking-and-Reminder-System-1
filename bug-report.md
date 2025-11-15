**Alumni Module Bug Report**


🔴 Critical
____________________

1. Start year vs. graduation year logic:
If a user is a "Student" or “Employed & Student,” check that start year is later than graduation year. Additionally, the graduation year must be later than the start year.

2. "Employed & Student" submission issue:
If a user selects "Employed & Student" in the employment status, the form submits successfully but does not store data in the employment_status column of the alumni_profile table and does not add a row in the alumni_documents table. Additionally, no data is displayed in the dashboard cards. [DONE]

🟠 High Priority
______________________

1. Submission clearing issue:
When a rejected profile is resubmitted, previously entered details appear in the form, but clicking submit clears all data and reopens the form incorrectly. The form should reset automatically and allow smooth resubmission.

2. Yellow rejection card display:
Rejection cards must appear in the dashboard, not only in the proceeding tab. It should match the style of the “Complete Your Profile” card.

🟡 Medium Priority 
______________________

1. Alumni data display:
After a successful submission, the Employment/Academic Details cards must be displayed on the dashboard using the same UI style as existing cards, positioned below the existing cards for consistency. Currently, these cards are displayed only in the Profile Management tab.

🟢 Low Priority 
______________________

1. Successful submission display issue:
Successful submission must appear on the dashboard like the “Complete Your Profile” card, but the color should be green.

2. Start & end year display:
If "Student" or "Employed & Student" is selected, start & end year values must display correctly in the Employment/Academic Details card after successful submission. 

3. Apostrophe handling:
Employment/Academic Details display cards after successful submission have issues with apostrophe rendering. [DONE]

4. Header bar scroll issue: The header bar must remain fixed and not be scrollable. [DONE]


______________________
______________________
______________________

**Admin Module Bug Report**

🟢 Low Priority 
______________________

1. Sidebar and Header Bar Improvements: Currently, both have a fixed height and are scrollable.

2. Recent Activity Log Page Refinement.
  
3. General UI Refinement.

4. ang admin inig human approve/reject, dapat stay lng sa page & d mu redirect sa batch display page.