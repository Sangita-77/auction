# Quiz System Plugin

A comprehensive quiz system for WordPress with custom post types, taxonomies, and frontend quiz functionality.

## Features

- **Custom Post Types**: Quiz Questions and Quiz Attempts
- **Taxonomy**: Quiz Branches for organizing questions
- **Question Types**: Radio buttons, checkboxes, and dropdowns
- **Progress Tracking**: Visual progress bar and question counter
- **Results Management**: Automatic scoring and detailed results storage
- **Email Notifications**: Results sent to users via email
- **Admin Dashboard**: View and manage all quiz attempts

## Installation

1. Upload the `quiz-system` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to 'Quiz Questions' in the admin menu to start creating questions

## Usage

### Creating Quiz Questions

1. Go to **Quiz Questions** → **Add New**
2. Enter a title for the question
3. Fill in the question details:
   - Question Text
   - Answer Type (Radio, Checkbox, or Dropdown)
   - Answer Options (add multiple options)
   - Mark correct answer(s)
4. Assign to a Quiz Branch (optional)
5. Publish the question

### Displaying Quizzes

Use the shortcode to display quizzes on any page or post:

```
[quiz]
```

To filter by branch:

```
[quiz branch="your-branch-slug"]
```

### Viewing Results

1. Go to **Quiz Attempts** in the admin menu
2. View all quiz submissions with scores and details
3. Click on any attempt to see detailed results

## File Structure

```
quiz-system/
├── quiz-system.php          # Main plugin file
├── includes/
│   ├── class-quiz-post-types.php
│   ├── class-quiz-admin.php
│   └── class-quiz-frontend.php
├── assets/
│   ├── css/
│   │   └── quiz-style.css
│   └── js/
│       └── quiz-script.js
└── README.md
```

## Requirements

- WordPress 5.0 or higher
- PHP 7.2 or higher

## License

GPL v2 or later

