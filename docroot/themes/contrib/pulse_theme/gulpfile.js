const gulp = require('gulp');
const gulpPostcss = require('gulp-postcss');
const postcss = require('postcss');
const tailwindcss = require('@tailwindcss/postcss');
const autoprefixer = require('autoprefixer');
const nested = require('postcss-nested');
const rename = require('gulp-rename');
const cleanCSS = require('gulp-clean-css');
const del = require('del');
const prettier = require('gulp-prettier');
const through = require('through2');
const path = require('path');

// Global error collector
const errorCollector = {
  errors: new Map(), // Map of file -> array of errors
  reset() {
    this.errors.clear();
  },
  addError(file, error) {
    const filePath = path.relative(process.cwd(), file);
    if (!this.errors.has(filePath)) {
      this.errors.set(filePath, []);
    }
    this.errors.get(filePath).push(error);
  },
  displayErrors() {
    if (this.errors.size === 0) return;
    
    console.error('\n❌ Found unknown utility classes:\n');
    
    const allUtilities = new Set();
    this.errors.forEach((errors, file) => {
      errors.forEach(err => {
        const match = err.match(/Cannot apply unknown utility class `([^`]+)`/);
        if (match) {
          allUtilities.add(match[1]);
        }
      });
    });
    
    const sortedUtilities = Array.from(allUtilities).sort();
    sortedUtilities.forEach((utility, index) => {
      console.error(`  ${index + 1}. ${utility}`);
    });
    
    console.error(`\n📊 Total: ${sortedUtilities.length} unique unknown utility classes\n`);
    
    // Also show which files have errors
    console.error('📁 Files with errors:');
    this.errors.forEach((errors, file) => {
      console.error(`   - ${file} (${errors.length} error${errors.length > 1 ? 's' : ''})`);
    });
    console.error('');
  }
};

// Task to clean old CSS files before rebuilding
gulp.task('clean', async () => {
  console.log('🧹 Cleaning old CSS files...');
  const deletedPaths = await del(['./components/**/*.css']);
  console.log(`✅ Deleted ${deletedPaths.length} CSS files`);
});

// Task to format .pcss files with Prettier
gulp.task('formatPCSS', () => {
  console.log('✨ Formatting PCSS files with Prettier...');
  return gulp
    .src('./components/**/*.pcss', { base: './' })
    .pipe(prettier({ tabWidth: 2, useTabs: false }))
    .pipe(gulp.dest('./'));
});

// Task to compile and minify .pcss to .css with Tailwind and Twig support
gulp.task('compilePCSS', () => {
  console.log('🔨 Compiling PCSS files to CSS...');
  
  // Reset error collector
  errorCollector.reset();
  
  return gulp
    .src('./components/**/*.pcss', { base: './' })
    .pipe(through.obj(async function(file, enc, cb) {
      // Process each file individually to catch errors per file
      const stream = this;
      
      if (file.isNull()) {
        return cb(null, file);
      }
      
      if (file.isStream()) {
        return cb(new Error('Streaming not supported'));
      }

      let currentContents = file.contents.toString();
      const maxRetries = 50; // Avoid infinite loops
      let retries = 0;
      let success = false;
      let finalCss = '';

      // Iteratively compile to find ALL errors in the file
      while (retries < maxRetries && !success) {
        // Suppress console.error during compilation to avoid noisy stack traces from Tailwind/PostCSS
        const originalConsoleError = console.error;
        console.error = function() {}; 

        try {
          const result = await postcss([nested(), tailwindcss(), autoprefixer()])
            .process(currentContents, {
              from: file.path,
              to: file.path.replace('.pcss', '.css')
            });
          
          finalCss = result.css;
          success = true;
        } catch (err) {
          // Restore console.error immediately to capture the error for our report
          console.error = originalConsoleError;
          
          const errorMsg = err.message || err.toString();
          const match = errorMsg.match(/Cannot apply unknown utility class `([^`]+)`/);
          
          if (match) {
            const unknownClass = match[1];
            // Log the error internally
            errorCollector.addError(file.path, `Cannot apply unknown utility class \`${unknownClass}\``);
            
            // Replace the unknown class with a safe fallback (e.g. 'block') to continue processing
            const escapedClass = unknownClass.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            const regex = new RegExp(escapedClass, 'g');
            
            if (!regex.test(currentContents)) {
               errorCollector.addError(file.path, `Failed to locate/replace unknown class \`${unknownClass}\` for subsequent error checking.`);
               break; 
            }
            
            currentContents = currentContents.replace(regex, 'block');
            retries++;
          } else {
            // Non-utility error, log and break
            errorCollector.addError(file.path, errorMsg);
            break; 
          }
        } finally {
          // Always restore console.error
          console.error = originalConsoleError;
        }
      }

      if (success && !errorCollector.errors.has(path.relative(process.cwd(), file.path))) {
        // Only update contents if it was a clean success (no errors found at all)
        // If we found errors, we don't want to write the "fixed" file with 'block' classes
        file.contents = Buffer.from(finalCss);
      } else {
        // If there were errors, we can't output valid CSS. 
        // We pass empty content or a comment so the build doesn't crash but outputs nothing useful.
        file.contents = Buffer.from('/* Error during compilation - check console logs */');
      }
      
      cb(null, file);
    }))
    .on('finish', function() {
      // Display all collected errors at the end
      errorCollector.displayErrors();
    })
    .pipe(
      rename((filePath) => {
        filePath.extname = '.css';
      })
    )
    .pipe(cleanCSS())
    .pipe(gulp.dest('./'));
});

// Watch task to monitor changes in .pcss and .twig files
gulp.task('watch', () => {
  gulp.watch(
    [
      './components/**/*.pcss',
      './components/**/*.twig',
      './templates/**/*.html.twig',
    ],
    gulp.series('clean', 'compilePCSS')
  );
});

// Default task (cleans old files and compiles)
gulp.task('default', gulp.series('formatPCSS', 'clean', 'compilePCSS'));
