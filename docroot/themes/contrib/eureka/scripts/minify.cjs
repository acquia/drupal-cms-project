const glob = require('glob');
const fs = require('fs-extra');
const terser = require('terser');
const { minify } = require('minify');

async function minifyFiles() {
  // Get all JS files (excluding already minified ones)
  const scripts = glob.sync('components/**/*.js', {
    ignore: ['components/**/*.min.js', 'components/**/*.stories.js'],
  });

  console.time('\x1b[36mMinified all JS files in\x1b[0m');
  for (const file of scripts) {
    try {
      const code = fs.readFileSync(file, 'utf8');

      // Minify JS while removing unused imports.
      const minified = await terser.minify(code, {
        module: true, // Keep ES module syntax if needed.
        compress: {
          passes: 2, // Optimize further.
        },
        format: {
          comments: false, // Remove comments.
        },
      });

      const minFile = file.replace(/\.js$/, '.min.js');
      fs.writeFileSync(minFile, minified.code);
    } catch (error) {
      console.error(`\x1b[31mError minifying ${file}:\x1b[0m`, error);
    }
  }
  console.timeEnd('\x1b[36mMinified all JS files in\x1b[0m');

  // Get all CSS files, excluding already minified ones.
  console.time('\x1b[36mMinified all CSS files in\x1b[0m');
  const baseStyles = glob.sync('components/{00-base,01-atoms}/**/*.css', {
    ignore: ['components/**/*.min.css'],
  });
  let baseOutput = '';

  for (const file of baseStyles) {
    try {
      const minified = await minify(file);
      baseOutput += minified;
    } catch (error) {
      console.error(`\x1b[31mError minifying ${file}:\x1b[0m`, error);
    }
  }
  if (baseOutput)
    fs.writeFileSync('components/00-base/global.min.css', baseOutput);

  const styles = glob.sync('components/**/*.css', {
    ignore: [
      'components/**/*.min.css',
      'components/00-base/**/*.css',
      'components/01-atoms/**/*.css',
    ],
  });

  for (const file of styles) {
    try {
      const minified = await minify(file);
      const minFile = file.replace(/\.css$/, '.min.css');

      fs.writeFileSync(minFile, minified);
    } catch (error) {
      console.error(`\x1b[31mError minifying ${file}:\x1b[0m`, error);
    }
  }
  console.timeEnd('\x1b[36mMinified all CSS files in\x1b[0m');
}

// Run the function.
minifyFiles();
