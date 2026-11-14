const tailwindcss = require('@tailwindcss/postcss');
const nested = require('postcss-nested');
const autoprefixer = require('autoprefixer');
const postrtl = require('./node_modules/postcss-rtlcss');

module.exports = {
  plugins: [
    tailwindcss, 
    nested, 
    autoprefixer, 
    postrtl
  ]
};
