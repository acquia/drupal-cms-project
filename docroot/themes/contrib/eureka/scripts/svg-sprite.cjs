const path = require('path');
const svgSprite = require('svg-sprite');
const fs = require('fs');
const glob = require('glob');

console.time('\x1b[36mSVG sprite generated successfully\x1b[0m');
console.log('\x1b[36mGenerating SVG sprite...\x1b[0m');

const config = {
  mode: {
    defs: true,
    symbol: true,
  },
  shape: {
    id: {
      generator: (name) => path.basename(name, '.svg'),
    },
  },
};

// Get all SVG files synchronously
const svgFiles = glob.sync('images/icons/**/*.svg');
const spriter = new svgSprite(config);

svgFiles.forEach((file) => {
  const fileName = path.basename(file);
  spriter.add(path.resolve(file), fileName, fs.readFileSync(file));
});

spriter.compile((err, result) => {
  if (err) {
    console.error('\x1b[31mFailed to compile sprites:\x1b[0m', err);
  } else {
    if ('symbol' in result) {
      for (let resource in result['symbol']) {
        fs.writeFileSync(
          `./images/icons.svg`,
          result['symbol'][resource].contents,
        );
      }
    }
    console.timeEnd('\x1b[36mSVG sprite generated successfully\x1b[0m');
  }
});
