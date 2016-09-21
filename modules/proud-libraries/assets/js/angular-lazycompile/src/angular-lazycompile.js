'use strict';

angular.module('angular-lazycompile', [
])

.directive('lazyCompile', ['$compile', '$timeout', function ($compile, $timeout) {
  return {
    scope: {
      lazyCompile: '=',
      lazyDecode: '=',
      lazyTimeout: '=',
      lazyTimeoutDur: '='
    },
    replace: true,
    link: function postLink(scope, element, attrs) {

      var voidCompile,
          timeout,
          rendered     = false,
          timeoutVal   = scope.lazyTimeout,
          timeoutDur   = scope.lazyTimeoutDur || 1000;

      // Compiles variable set in lazy-compile 
      var compile = function(value) {
        if(value && value != "false") {
          if(scope.lazyDecode) {
            value = decodeURIComponent(value);
          }
          // when the 'compile' expression changes
          var lazyContent = angular.element(value);
          // Add after our element
          element.after(lazyContent);

          // compile the new DOM
          $compile(lazyContent)(scope.$parent);

          setTimeout(function() {
            scope.$destroy();
            scope = null;

            element.remove();
            element = null;
          }, 0);
          // Set rendered
          rendered = true;
          // Use un-watch feature to ensure compilation happens only once.
          voidCompile();
          // Cancel timeout if still waiting
          if(timeout) {
            $timeout.cancel(timeout);
          }
        }
      }
      // Set Watch
      voidCompile = scope.$watch('lazyCompile', function(value, oldVal) {
        var doRender = !rendered && value && value !== "false" && value !== oldVal;
        if(doRender) {
          compile(value);
        }
      });
      // if lazy-timeout is set, start timeout
      if(timeoutVal) {
        timeout = $timeout(function() {
          if(!rendered) {
            compile(timeoutVal);
          }
        }, parseInt(timeoutDur));
      }
    }
  }
}]);