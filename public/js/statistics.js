app.controller('StatisticsController', ['$scope', function ($scope) {
  $scope.customers = [
            {name: 'John Smith', city: 'Phoenix'},
            {name: 'Jane Smith', city: 'Pittsburgh'},
            {name: 'John Doe', city: 'New York'},
            {name: 'Jane Doe', city: 'Los Angeles'}
        ];
}]);