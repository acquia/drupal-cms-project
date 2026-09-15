#!/bin/bash

# This is a simple script to pull down the specified version of editoria11y from github

GIT_REF="2.4.x"

mkdir -p tmp/
cd tmp/
git clone git@github.com:itmaybejj/editoria11y.git .
git checkout $GIT_REF
rm -rf ../library/js
rm -rf ../library/css
rm -rf ../library/dist
mv js ../library/js
mv css ../library/css

mv dist ../library/dist
cd ../
rm -rf tmp

# Get library version number.
filename="library/js/ed11y.js"
regex=".*(Ed11y\.version = ')(.*)(';)"
while IFS= read -r line; do
  if [[ "$line" =~ $regex ]]; then
    ED11YV=${BASH_REMATCH[2]}
  fi
done < "$filename"

sed -i -E "s/\(  version: \)\(.*\)/\1${ED11YV}/g" editoria11y.libraries.yml

# MacOS creates unwanted backup files
rm editoria11y.libraries.yml-E
