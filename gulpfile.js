'use strict';

const gulp = require('gulp');
const sass = require('gulp-sass')(require('sass'));
const autoprefixer = require('gulp-autoprefixer');

function buildStyles() {
  return gulp.src('./assets/src/scss/*.scss')
    .pipe(sass().on('error', sass.logError))
    .pipe(autoprefixer({cascade: false}))
    .pipe(gulp.dest('./assets/css'));
};

exports.buildStyles = buildStyles;
exports.watch = function () {
  gulp.watch('./assets/src/scss/**/*.scss', gulp.series(buildStyles));
};
