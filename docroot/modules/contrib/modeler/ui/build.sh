#!/bin/bash
# Build script for Workflow Modeler
# Usage: ./build.sh [--production]
# Default: builds for development (unminified with sourcemaps)
# With --production: builds for production (minified without sourcemaps)
#
# Features:
# - TypeScript type checking (if tsc is available)
# - esbuild bundling with React/TypeScript support
# - CSS concatenation and minification
# - Development/Production modes

# Get the directory where this script is located
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"
cd "$SCRIPT_DIR"

# ============================================================================
# Dependencies Validation
# ============================================================================

validate_dependencies() {
  local missing_deps=()
  local critical_packages=(
    "react"
    "react-dom"
    "reactflow"
    "esbuild"
    "typescript"
    "zustand"
    "dompurify"
    "js-yaml"
  )

  # Check if node_modules directory exists
  if [ ! -d "node_modules" ]; then
    echo "❌ Error: node_modules directory not found!"
    echo ""
    echo "   Please install dependencies first:"
    echo "   npm install"
    echo ""
    return 1
  fi

  # Check for critical packages
  for package in "${critical_packages[@]}"; do
    if [ ! -d "node_modules/$package" ]; then
      missing_deps+=("$package")
    fi
  done

  # Report missing packages
  if [ ${#missing_deps[@]} -gt 0 ]; then
    echo "❌ Error: Missing critical dependencies!"
    echo ""
    echo "   The following packages are not installed:"
    for dep in "${missing_deps[@]}"; do
      echo "   - $dep"
    done
    echo ""
    echo "   Please run: npm install"
    echo ""
    return 1
  fi

  # Validate package.json exists
  if [ ! -f "package.json" ]; then
    echo "❌ Error: package.json not found!"
    echo "   Build must be run from the ui/ directory."
    return 1
  fi

  echo "✅ Dependencies validated successfully"
  return 0
}

# Run dependency validation
echo "Validating dependencies..."
if ! validate_dependencies; then
  exit 1
fi

# Check if production mode is requested
PRODUCTION_MODE=false
VALIDATE_MODE=false
STANDALONE_MODE=false
for arg in "$@"; do
  case "$arg" in
    --production) PRODUCTION_MODE=true ;;
    --no-validate) ;;
    --standalone) STANDALONE_MODE=true ;;
  esac
done

if [[ "$PRODUCTION_MODE" == "true" ]]; then
  BUILD_TYPE="production"
  NODE_ENV="production"
  echo "Building Workflow Modeler for production..."
elif [[ "$1" == "--no-validate" ]]; then
  BUILD_TYPE="development"
  NODE_ENV="development"
  echo "Building Workflow Modeler for development without validation..."
else
  VALIDATE_MODE=true
  BUILD_TYPE="development"
  NODE_ENV="development"
  echo "Building Workflow Modeler for development..."
fi

echo "Working directory: $(pwd)"

# In development mode, run type checks and linter
if [[ "$VALIDATE_MODE" == "true" ]]; then

  # Run TypeScript type checking (if available)
  echo "Running TypeScript type checking..."
  npm run type-check
  TYPE_CHECK_EXIT_CODE=$?

  if [ $TYPE_CHECK_EXIT_CODE -eq 0 ]; then
    echo "✅ TypeScript type checking passed!"
  elif [ $TYPE_CHECK_EXIT_CODE -eq 127 ]; then
    echo "⚠️  TypeScript compiler not available, skipping type checking..."
    echo "   To enable type checking, install TypeScript: npm install typescript"
  else
    echo "⚠️  TypeScript type checking found issues but continuing build..."
    echo "   Run 'npm run type-check' to see type errors in detail"
  fi

  # Run ESLint (if available)
  echo "Running ESLint..."
  npm run lint
  LINT_EXIT_CODE=$?

  if [ $LINT_EXIT_CODE -eq 0 ]; then
    echo "✅ ESLint passed!"
  elif [ $LINT_EXIT_CODE -eq 127 ]; then
    echo "⚠️  ESLint not available, skipping linting..."
    echo "   To enable linting, install ESLint: npm install"
  else
    echo "⚠️  ESLint found issues but continuing build..."
    echo "   Run 'npm run lint' to see linting errors in detail"
    echo "   Run 'npm run lint:fix' to automatically fix some issues"
  fi
fi

# Create dist directory if it doesn't exist (in module root, one level up)
mkdir -p ../dist

# Note: React and ReactDOM are now bundled directly, no external files needed

# Build the main bundle with esbuild
if [[ "$PRODUCTION_MODE" == "true" ]]; then
  echo "Building main bundle with esbuild (production mode with minification)..."
  npx esbuild src/index.js \
    --bundle \
    --outfile=../dist/modeler.bundle.js \
    --format=iife \
    --platform=browser \
    --target=es2017 \
    --loader:.js=jsx \
    --loader:.jsx=jsx \
    --loader:.ts=tsx \
    --loader:.tsx=tsx \
    --jsx-factory=React.createElement \
    --jsx-fragment=React.Fragment \
    --define:global=window \
    --define:process.env.NODE_ENV='"production"' \
    --minify \
    --tree-shaking=true \
    --external:drupal \
    --external:drupalSettings \
    --external:jquery
else
  echo "Building main bundle with esbuild (development mode - unminified with sourcemaps)..."
  npx esbuild src/index.js \
    --bundle \
    --outfile=../dist/modeler.bundle.js \
    --format=iife \
    --platform=browser \
    --target=es2017 \
    --loader:.js=jsx \
    --loader:.jsx=jsx \
    --loader:.ts=tsx \
    --loader:.tsx=tsx \
    --jsx-factory=React.createElement \
    --jsx-fragment=React.Fragment \
    --define:global=window \
    --define:process.env.NODE_ENV='"development"' \
    --sourcemap \
    --external:drupal \
    --external:drupalSettings \
    --external:jquery
fi

# Bundle CSS files
if [[ "$PRODUCTION_MODE" == "true" ]]; then
  echo "Building CSS bundle (production mode with minification)..."
  # First concatenate the CSS files to a temp file
  cat node_modules/reactflow/dist/style.css src/styles/modeler.css > ../dist/modeler.bundle.temp.css
  # Then minify the concatenated file
  npx esbuild ../dist/modeler.bundle.temp.css \
    --outfile=../dist/modeler.bundle.css \
    --loader:.css=css \
    --minify \
    --allow-overwrite
  # Clean up temp file
  rm -f ../dist/modeler.bundle.temp.css
else
  echo "Building CSS bundle (development mode - unminified)..."
  # Concatenate the CSS files directly without minification
  cat node_modules/reactflow/dist/style.css src/styles/modeler.css > ../dist/modeler.bundle.css
fi

# ============================================================================
# Standalone Viewer Build (optional, triggered by --standalone)
# ============================================================================

if [[ "$STANDALONE_MODE" == "true" ]]; then
  echo ""
  echo "Building standalone viewer bundle..."

  if [[ "$PRODUCTION_MODE" == "true" ]]; then
    echo "  Standalone bundle (production mode with minification)..."
    npx esbuild src/standalone.tsx \
      --bundle \
      --outfile=../dist/modeler-viewer.bundle.js \
      --format=iife \
      --platform=browser \
      --target=es2017 \
      --loader:.js=jsx \
      --loader:.jsx=jsx \
      --loader:.ts=tsx \
      --loader:.tsx=tsx \
      --jsx-factory=React.createElement \
      --jsx-fragment=React.Fragment \
      --define:global=window \
      --define:process.env.NODE_ENV='"production"' \
      --minify \
      --tree-shaking=true
  else
    echo "  Standalone bundle (development mode - unminified with sourcemaps)..."
    npx esbuild src/standalone.tsx \
      --bundle \
      --outfile=../dist/modeler-viewer.bundle.js \
      --format=iife \
      --platform=browser \
      --target=es2017 \
      --loader:.js=jsx \
      --loader:.jsx=jsx \
      --loader:.ts=tsx \
      --loader:.tsx=tsx \
      --jsx-factory=React.createElement \
      --jsx-fragment=React.Fragment \
      --define:global=window \
      --define:process.env.NODE_ENV='"development"' \
      --sourcemap
  fi

  # The standalone viewer reuses the same CSS bundle
  cp ../dist/modeler.bundle.css ../dist/modeler-viewer.bundle.css

  echo "  Standalone viewer build complete!"
fi

echo ""
echo "$BUILD_TYPE build complete!"
echo "Files in dist/:"
ls -la ../dist/
