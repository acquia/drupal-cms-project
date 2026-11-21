# Remote npm/npx Execution (Docker)

> **Prerequisite**: This document applies only when the `remote-npm` Claude Code skill is installed. If the skill is not present, npm and npx commands run locally as normal. See [Installing the remote-npm skill](#installing-the-remote-npm-skill) below for setup instructions.

When the skill is present, all npm and npx commands are executed inside a Docker container on a remote host over SSH. The local `ui/` directory is synced to the remote, commands run in a Node container there, and results are synced back. No Node.js installation is required on the remote host -- only Docker.

## How It Works

The `remote-npm` skill is automatically loaded by Claude Code whenever it encounters npm or npx commands. The skill transparently wraps each command through a shell script that handles the full lifecycle:

1. **Sync Up**: rsync `ui/` to the remote host (excluding `node_modules`, `.git`, etc.)
2. **Docker Setup**: Ensure the Node image is pulled and a named volume exists for `node_modules`
3. **Install**: Run `npm install` inside the container if `node_modules` is missing or stale
4. **Playwright**: Auto-detect Playwright/E2E/Storybook commands and install browsers via `npx playwright install --with-deps chromium`
5. **Execute**: Run the npm/npx command inside a `docker run` container on the remote host
6. **Sync Back**: rsync results (build artifacts, test output, coverage) back to the local machine

All commands in the codebase are written as plain `npm`/`npx` invocations. The skill wraps them transparently — no command changes are needed. Developers without the skill simply run commands locally.

## Architecture

```
Local Machine                    Remote Host
+----------------+              +------------------------------------------+
|                |    rsync     |                                          |
|  ui/           | ----------> |  REMOTE_PATH/ui/                         |
|  (source)      |              |  (synced source)                         |
|                |              |                                          |
|                |    SSH       |  docker run node:22-bookworm             |
|                | ----------> |    -v REMOTE_PATH:/project                |
|                |              |    -v modeler-npm-node_modules            |
|                |              |        :/project/ui/node_modules          |
|                |              |    -v modeler-npm-playwright              |
|                |              |        :/root/.cache/ms-playwright        |
|                |              |    -w /project/ui                         |
|                |              |    npm run build                          |
|                |              |  (../dist -> /project/dist/ in mount)    |
|                |              |                                          |
|  ui/           |    rsync     |  REMOTE_PATH/ui/                         |
|  ../dist/      | <---------- |  REMOTE_PATH/dist/                       |
|  (with output) |              |  (with build artifacts)                  |
+----------------+              +------------------------------------------+
```

The `REMOTE_PATH` is mounted as `/project`, with the working directory set to `/project/ui`. This preserves the project layout so that `../dist` from `ui/` resolves to `/project/dist/` (still within the bind mount). Both `node_modules` and Playwright browser cache are persisted in Docker named volumes.

### Why Docker?

- **No host dependencies**: The remote host only needs Docker, not Node.js
- **Reproducible**: Same Node version regardless of host OS
- **Isolated**: npm packages cannot affect the host system
- **Persistent node_modules**: A Docker named volume (`modeler-npm-node_modules`) persists `node_modules` across runs, avoiding repeated installs


## Configuration

Environment variables configure the remote connection. They can be provided via a `.claude/.env` file (recommended) or exported in the shell environment. Shell environment takes precedence over `.env` values.

### Example `.claude/.env`

```bash
REMOTE_HOST=user@myserver.example.com
REMOTE_PATH=/home/user/modeler-build
REMOTE_SSH_KEY=~/.ssh/id_ed25519
DOCKER_NODE_IMAGE=node:22-bookworm
REMOTE_VERBOSE=0
```

### Required Variables

- `REMOTE_HOST` - SSH host target (e.g., `user@myserver.com`)
- `REMOTE_PATH` - Remote working directory (e.g., `/home/user/modeler-build`)

### Optional Variables

| Variable | Default | Description |
|---|---|---|
| `REMOTE_USER` | (from REMOTE_HOST) | SSH user (overrides user@ in REMOTE_HOST) |
| `REMOTE_PORT` | `22` | SSH port |
| `REMOTE_SSH_KEY` | (none) | Path to SSH private key file |
| `REMOTE_SYNC_BACK` | `1` | Set to `0` to skip syncing results back |
| `REMOTE_NPM_INSTALL` | `1` | Set to `0` to skip npm install inside container |
| `REMOTE_RSYNC_OPTS` | (none) | Additional rsync options (e.g., `--delete --compress`) |
| `REMOTE_VERBOSE` | `0` | Set to `1` for verbose output |
| `LOCAL_UI_DIR` | (auto-detected) | Override local ui directory path |
| `DOCKER_NODE_IMAGE` | `node:22-bookworm` | Docker image to use for Node.js |
| `DOCKER_CONTAINER` | `modeler-npm` | Container name prefix (used for volume naming) |
| `DOCKER_EXTRA_OPTS` | (none) | Additional `docker run` options (e.g., `--memory=4g --cpus=2`) |

### Prerequisites

- SSH keys configured for passwordless authentication (`BatchMode=yes`)
- `rsync` installed on both local and remote machines
- Docker installed on the remote host
- Remote SSH user in the `docker` group (or root)
- Remote host reachable from the local machine

## What Gets Synced

### Upload (local -> remote)

The entire `ui/` directory is synced, **excluding**:
- `node_modules` (managed via Docker volume)
- `.git`
- `tests/coverage`
- `.cache`
- `*.tsbuildinfo`
- `tests/test-results`
- `tests/playwright-report`
- `tests/storybook-static` (build artifact, generated on remote)

### Download (remote -> local)

Everything in the remote `ui/` directory is synced back, **excluding**:
- `node_modules`
- `.git`
- `.cache`

This means build artifacts (`../dist/`, `tests/storybook-static/`), test results, coverage reports, and any generated files are available locally after execution.

### Docker Volumes

Two Docker named volumes persist across container runs:

- **`modeler-npm-node_modules`**: Stores `node_modules`, mounted at `/project/ui/node_modules`. `npm install` only runs when `package.json` changes.
- **`modeler-npm-playwright`**: Stores Playwright browser binaries, mounted at `/root/.cache/ms-playwright`. Browsers persist across runs so they don't need to be re-downloaded each time.

Note: For Playwright/E2E/Storybook commands, the browser install and test run execute in the **same container** (via `bash -c "install && test"`). This is necessary because `--with-deps` installs system packages (apt) that don't persist in volumes.

## How It Integrates with Existing Workflows

All commands documented in the [Build and Quality Pipeline](build-commands.md) work identically whether run locally or through the remote skill. The exit code from the container is preserved, so error detection and CI integration work as expected.

### Example: Full Development Pipeline

```bash
# These commands work locally or are transparently wrapped by the skill
npm run dev     # lint, typecheck, and build
npm test        # unit tests
npm run e2e     # E2E tests
```

## Troubleshooting

### Common Issues

1. **"REMOTE_HOST is not set"**
   - Create a `.claude/.env` file with the required connection variables

2. **SSH permission denied**
   - Ensure SSH keys are configured: `ssh-copy-id $REMOTE_HOST`
   - The script uses `BatchMode=yes` so it will not prompt for passwords

3. **rsync not found**
   - Install rsync on both machines: `apt install rsync` or `brew install rsync`

4. **Docker permission denied**
   - Add the SSH user to the docker group: `ssh $REMOTE_HOST "sudo usermod -aG docker \$USER"`
   - Log out and back in for the group change to take effect

5. **Docker image pull fails**
   - Check internet access on the remote host
   - Pre-pull the image: `ssh $REMOTE_HOST "docker pull node:22-bookworm"`

6. **Stale node_modules**
   - Force a fresh install by removing the volume:
     `ssh $REMOTE_HOST "docker volume rm modeler-npm-node_modules"`

7. **Build artifacts not appearing locally**
   - Check `REMOTE_SYNC_BACK` is not set to `0`
   - Verify the container produced output: run with `REMOTE_VERBOSE=1`

### Performance Tips

- Use `REMOTE_RSYNC_OPTS="--compress"` for slow connections
- Use `REMOTE_RSYNC_OPTS="--delete"` to keep remote directory clean
- Set `REMOTE_SYNC_BACK=0` when you only need the command output (e.g., linting)
- Set `REMOTE_NPM_INSTALL=0` after the first run if dependencies haven't changed
- Use `DOCKER_EXTRA_OPTS="--memory=4g --cpus=2"` to constrain or allocate container resources

### Docker Image Options

The default image is `node:22-bookworm` (full Debian with bash and build tools, matches CI). Playwright browsers are auto-installed when E2E or Storybook test commands are detected. Other image options:

| Image | Size | Use Case |
|---|---|---|
| `node:22-bookworm` | ~1GB | Default, full Debian, has bash, supports Playwright install |
| `node:22-slim` | ~240MB | Debian-based, lighter but has bash |
| `node:22-alpine` | ~180MB | Smallest, but lacks bash (build.sh won't work) |

Set via `DOCKER_NODE_IMAGE` in `.claude/.env` or the shell environment.

## Installing the remote-npm Skill

If the `remote-npm` skill is not installed, npm and npx commands run on the local machine directly. To install it:

1. **Obtain the skill files** and place them in `.claude/skills/remote-npm/` under the `ui/` directory.

2. **Create `.claude/.env`** with the required connection variables:

   ```bash
   REMOTE_HOST=user@myserver.example.com
   REMOTE_PATH=/home/user/modeler-build
   ```

3. **Ensure prerequisites** on the remote host:
   - Docker installed and accessible by the SSH user
   - SSH key-based authentication configured (the script uses `BatchMode=yes`)
   - `rsync` installed on both local and remote machines

4. **Verify** the setup by running any npm command through Claude Code — the skill will be automatically loaded and used.

The `.claude/` directory is listed in `.gitignore`, so the skill and its configuration are local to your development environment and not committed to the project repository.
