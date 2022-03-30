'use strict';

const gulp = require('gulp');
const sass = require('gulp-sass')(require('sass'));
const autoprefixer = require('gulp-autoprefixer');
const cleanCSS = require('gulp-clean-css');

function buildStyles() {
  return gulp.src('./assets/src/scss/*.scss')
    .pipe(sass().on('error', sass.logError))
    .pipe(autoprefixer({cascade: false}))
    .pipe(cleanCSS({compatibility: 'ie11'}))
    .pipe(gulp.dest('./assets/css'));
};

function buildAdminStyles(cb) {
  return gulp.src('./assets/src/admin/admin.scss')
    .pipe(sass().on('error', sass.logError))
    .pipe(autoprefixer({cascade: false}))
    .pipe(cleanCSS({compatibility: 'ie11'}))
    .pipe(gulp.dest('./assets/css'));
};

exports.buildStyles = buildStyles;
exports.watch = function () {
  gulp.watch('./assets/src/scss/**/*.scss', gulp.series([buildStyles]));
};

exports.buildAdminStyles = buildAdminStyles;
exports.watch = () => {
  gulp.watch('./assets/src/admin/**/*.scss', gulp.series([buildAdminStyles]));
}
