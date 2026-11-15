# Joomla.org Template
This is the Joomla! 4.x version of the `joomla.org` site template.

## Dev Requirements
If only making changes to the template's layout files, you only need to clone this repository.

If making changes to the template's SCSS files, you must perform these additional steps:

- Ensure [`npm`](https://www.npmjs.com/) is installed
- Run `npm ci` to install dependencies
- After making changes to the SCSS files, run the `npm run compile` task

>Important! **NEVER EVER edit the CSS directly**! Edit and compile SCSS or your **CSS changes WILL BE LOST!**

The package.json build is adapted to the repo from [this](https://stackoverflow.com/a/69196785) post on Stackoverflow

## Build the Package
To build a distributable/installable package, ensure you have the dev requirements set up then run: `npm run package`

## SCSS CSS
If you need to add some custom CSS (which should be very infrequently), add it to template.scss. **Try to stick to markup** creativity and don't write "one-off styles".

## Dynamic Column Widths
The template is configured to a default 3/6/3 row for the main content section.  The sidebar columns can have their widths customised via the template's parameters and the center column will be dynamically calculated from this.

## RTL Support
Some sites are localised and have RTL language support.  There is a RTL CSS file specific to this template loaded when needed.

## Custom CSS Support
For sites needing additional CSS rules, there is support for a `custom.css` file placed in the `css` directory.  For custom RTL CSS, use a `custom-rtl.css` file.

## Module Positions
This template uses the position-# approach for backwards compatibility from the old sites, using position-1,2,3,7,8 (and "search" and a "debug"" position)

### Search Position
The search position is rendered at `position-0`

### Top Fixed Nav
The top nav is hardcoded in the template since it's the global template for all J sites and not a dynamic menu module.  It is loaded from the Joomla CDN node.

### Affixed Sub Nav
The main menu module of the site belongs in `position-1`. This menu module will affix when scrolled past (sticky).

### Module Position Layout

The module positions can be seen in [this screenshot](https://cl.ly/311X1k0k0P1R)

## CDN Elements
The template uses the `joomla.org` CDN to load HTML snippets that are common to the template and may be updated more frequently than the base template itself.  The technical details:

- The `cdn/renderer.php` file is deployed at `https://cdn.joomla.org/template/renderer.php` and serves all requests for the elements
- The `cdn/layouts` directory is deployed at `https://cdn.joomla.org/template/layouts` and has all of the HTML snippets

The template itself will cache (without an option) these elements to help keep some of the traffic load on our webservers down.  Non-CMS uses of the template should do the same.

### Mega Menu

The mega menu is loaded by making a request to `https://cdn.joomla.org/template/renderer.php?section=menu&language=<lang_code>`.  The section element is required, the language element must match with one of the CMS' language codes and will default to en-GB if the section in the requested language isn't available.

This layout includes the `<jdoc>` tag for the search position (`position-0`).  Non-CMS apps should handle replacing this as needed.

### Footer

The mega menu is loaded by making a request to `https://cdn.joomla.org/template/renderer.php?section=footer&language=<lang_code>`.  The section element is required, the language element must match with one of the CMS' language codes and will default to en-GB if the section in the requested language isn't available.

This layout includes several placeholders for contextually aware content.  The template helper for the CMS will replace these elements automatically, non-CMS apps will need to handle conversions manually.  The placeholders are:

- `%reportroute%` - The URL to be used for the "Report an Issue" link for the site
- `%loginroute%` - The URL to log users into the site (if login functionality isn't used, it's suggested to just remove this bullet point completely)
- `%logintext%` - The text to display on the login link (if login functionality isn't used, it's suggested to just remove this bullet point completely)
- `%currentyear%` - The current year for the copyright statement, this should be `date('Y')` or the equivalent in other scripting languages

## Translation Management

The template is translated and [on Crowdin](https://joomla.crowdin.com/joomla-official-website-template).
