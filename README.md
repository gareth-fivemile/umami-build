# Composer build for the Drupal Umami installation profile and theme project

For more information including installation instructions visit [The Out of the Box Initiative issue on drupal.org](https://www.drupal.org/project/ideas/issues/2847582).

## Usage

```
composer install
cd web
drush si demo_umami --account-pass=pass --account-mail="your-email@example.com"
drush en demo_umami_content -y
```

## Platform.sh and visual regression testing

If you like to test some patch visually you need to switch to one of
CI branches (test-environment-[1,2,3]) and add a patch to composer.json

For example
```
"patches": {
    "drupal/core": {
        "Umami's language-switcher as a drop-down menu": "https://www.drupal.org/files/issues/2019-05-18/ootb-language-switcher-dropdown-3042417-40.patch"
    }
}
```

and push the code to github.

Platform.sh will deploy the patch to appropriate environment and will 
run visual regression testing (compares with master branch).
