'use strict';

angular.module('angular-lazycompile', [
])

.directive('lazyCompile', ['$compile', function ($compile) {
  return {
    scope: {
      lazyCompile: '=',
      lazyDecode: '='
    },
    replace: true,
    link: function postLink(scope, element, attrs) {
      var voidCompile = scope.$watch('lazyCompile', function(value) {
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

          // Use un-watch feature to ensure compilation happens only once.
          voidCompile();
        }
      });
    }
  }
}]);