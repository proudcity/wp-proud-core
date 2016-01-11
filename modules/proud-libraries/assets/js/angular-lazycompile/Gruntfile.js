'use strict';

module.exports = function(grunt) {

  require('load-grunt-tasks')(grunt);

  grunt.initConfig({
    pkg: grunt.file.readJSON('package.json'),
    src: 'src',
    dist: 'dist',

    jshint: {
      options: {
        jshintrc: '.jshintrc'
      },
      all: [
        'Gruntfile.js',
        '<%= src %>/js/**/*.js'
      ]
    },

    uglify: {
      options: {
        preserveComments: 'some',
        mangle: false,
        enclose: {'window':'window'}
      },
      dist: {
        files: {
          '<%= dist %>/angular-lazycompile.min.js': ['<%= src %>/angular-lazycompile.js']
        }
      }
    }
  });
  
  grunt.registerTask('default', [
    'jshint',
    'uglify'
  ]);
};
