'use strict';

const gulp = require('gulp');
const sass = require('gulp-sass')(require('sass'));

function buildStyles() {
  return gulp.src('./assets/src/scss/*.scss')
    .pipe(sass().on('error', sass.logError))
    .pipe(gulp.dest('./assets/css'));
};

exports.buildStyles = buildStyles;
exports.watch = function () {
  gulp.watch('./assets/src/scss/**/*.scss', gulp.series(buildStyles));
};
