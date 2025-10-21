## Play

### Setup

```bash
# Install ddev (Arch)
yay -S ddev-bin

# Install local development certificates
mkcert -install
```

### Development

```bash
# Start the ddev container
ddev start

# Start the nuxt development server
ddev pnpm dev
```

App will be served on `https://play.ddev.site`

### Storage Link

Generally, `artisan storage:link` fails to create the correct link because `public_path('storage')` and `storage_path('app/public')` point either to the local path or the container path. When the command is run in the local machine, it will be wrong in the container, and the opposite when run from the container.

Fix this by requiring the `symfony/filesystem` composer package and creating a relative link with `artisan storage:link --relative`
