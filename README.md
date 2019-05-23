# Composer build for the Drupal Umami installation profile and theme project  

For more information including installation instructions visit [The Out of the Box Initiative issue on drupal.org](https://www.drupal.org/project/ideas/issues/2847582).

## Usage

```
composer install
cd web
drush si demo_umami --account-pass=pass --account-mail="your-email@example.com"
drush en demo_umami_content -y
```

