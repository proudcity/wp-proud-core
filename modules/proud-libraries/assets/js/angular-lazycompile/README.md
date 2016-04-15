# LazyCompile
Angular lazy-compiling directive


#### Takes in html code as its lazy-compile variable

Controller
```
.controller($scope, function() {
  // Empty / null initially
  $scope.directiveCompile = null;
  
  // Provide html to execute later,
  // now directive is rendered on page
  $scope.someEvent = function() {
    $scope.directiveCompile = "<div my-directive></div>";
  }
});
```
View
```
<div lazy-compile="directiveCompile"></div>
```

#### Use url encoded html with the lazy-decode attribute

Controller
```
.controller($scope, function() {
  // Empty / null initially
  $scope.directiveCompile = null;
  
  // Provide html to execute later,
  // now directive is rendered on page
  $scope.someEvent = function() {
    $scope.directiveCompile = "%3Cdiv%20my-directive%3E%3C%2Fdiv%3E";
  }
});
```
View
```
<div lazy-compile="directiveCompile" lazy-decode="true"></div>
```
#### Add a timeout override
Controller
```
.controller($scope, function() {
  // Empty / null initially
  $scope.directiveCompile = null;
  
  // Provide html to execute later,
  // now directive is rendered on page
  $scope.someEvent = function() {
    $scope.directiveCompile = "%3Cdiv%20my-directive%3E%3C%2Fdiv%3E";
  }
});
```
View
```
<div lazy-compile="directiveCompile" lazy-decode="true" lazy-timeout="'%3Cdiv%20my-directive%3E%3C%2Fdiv%3E'" lazy-timeout-dur="2000"></div>
```
The directive will render when the event fires OR when 2 seconds have passed
#### Especially useful with something like https://github.com/thenikso/angular-inview
View
```
 <div in-view="directiveCompile = directiveCompile || someEvent()" 
      lazy-compile="directiveCompile" lazy-decode="true" lazy-timeout="'%3Cdiv%20my-directive%3E%3C%2Fdiv%3E'" lazy-timeout-dur="2000"></div>
```
Loads directive when user scrolls into view OR 2 seconds have passed
