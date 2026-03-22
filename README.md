# synaplan-templatex

Synaplan plugin to assist in template work: gather data and fill prepared Word documents. Based on an HR customer request.

## Installation

```bash
# Copy plugin into Synaplan
cp -r templatex-plugin /path/to/synaplan/plugins/templatex

# Clear cache
cd /path/to/synaplan && php bin/console cache:clear

# Install for a user
php bin/console app:plugin:install <userId> templatex
```

## Plugin Structure

```
templatex-plugin/
├── manifest.json           # Plugin metadata, routes, config schema
├── backend/
│   └── Controller/
│       └── TemplateXController.php
├── frontend/
│   └── index.js            # Vanilla JS SPA
└── migrations/
    └── 001_setup.sql       # Per-user BCONFIG setup
```

## Development

Plugin code is developed here and deployed by copying `templatex-plugin/` into Synaplan's `plugins/templatex/` directory.
