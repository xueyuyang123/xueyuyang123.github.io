# xueyuyang123.github.io
# Poll & Survey System

## Overview
This is a web-based polling and survey system built using PHP, MySQL (via phpMyAdmin), and hosted on an XAMPP Apache server. Users can create and participate in polls and surveys, with results tracking and filtering capabilities.

## System Requirements
- XAMPP with Apache server
- PHP (version 7.0 or higher recommended)
- MySQL (via phpMyAdmin)
- Web browser

## Installation
1. Install XAMPP from [https://www.apachefriends.org/](https://www.apachefriends.org/)
2. Start Apache and MySQL services from the XAMPP control panel
3. Clone or place this project in the `htdocs` folder of your XAMPP installation
4. Import the database schema using phpMyAdmin (provided SQL file)
5. Configure database connection in `config.php`

## Features

### User Authentication
- New users can register for an account
- Existing users can log in with their credentials
- Users can view their profile information on the Home page

### Poll/Survey Creation
- Click "Create Poll" to make a new voting system
- Click "Create Survey" to make a new questionnaire
- Enter questions and options for participants
- Finalize creation by clicking "Create Poll" or "Create Survey" button

### Voting Participation
- Browse all available polls/surveys on the Home page
- Select a poll/survey and click "Vote" to participate
- Submit votes by clicking "Complete"

### Results Viewing
- Click "Result" in the navigation bar to view voting results
- Filter options:
  - "Show All Poll" - displays all polls/surveys in the system
  - "Show Poll or Survey that You Created" - shows only your created items
  - "Polls and Surveys I Participate In" - shows items you've voted on
- View total votes for each option in polls/surveys

## Navigation
The system features a consistent top navigation bar with:
- **Home** (profile and poll browsing)
- **Create Poll**
- **Create Survey**
- **Results**
- **Logout**

## Troubleshooting
If you encounter issues:
1. Verify Apache and MySQL are running in XAMPP
2. Check database connection settings in `config.php`
3. Ensure all database tables were imported correctly
4. Verify file permissions in the project directory
