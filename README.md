# WPU Attachments Enhanced

 [![PHP workflow](https://github.com/WordPressUtilities/wpu_attachments_enhanced/actions/workflows/php.yml/badge.svg 'PHP workflow')](https://github.com/WordPressUtilities/wpu_attachments_enhanced/actions)

Add enhancements to attachments in WordPress

## Features

### Disable deletion

Adds a "Protected against deletion" checkbox on attachments (edit page and media modal).

- While checked, nobody can delete the attachment from the back-office, administrators included : uncheck it first.
- Only users with the minimum capability can toggle it. Others see a read-only notice.
- A lock icon flags protected attachments in the media library (list and grid modes).

Change the minimum capability required to toggle the protection (default : `manage_options`) :

```php
add_filter('wpu_attachments_enhanced__protected_min_cap', function ($cap) {
    return 'edit_others_posts';
});
```
