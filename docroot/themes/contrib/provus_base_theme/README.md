# About
provus_base_theme

## License
This project is licensed under `GPL-3.0-or-later`. See `LICENSE.txt` for the
full license text.

## Compiling using a Docker container
- SSH into the docker container
- Go to the root of this theme (`web/themes/custom/provus_base_theme`)
- Run `npm install`
- Run `npm install gulp --global`
- Run `gulp`
- Alternately run `gulp build_watch`

## Notes
The `build_watch` argument compiles the CSS and starts a watcher so changes are
rebuilt automatically. Without this argument, the build runs once and exits.
