<!DOCTYPE html>
<html>
<head>
    <title>Dashboard - Social Bee</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>

<div class="d-flex">

    <!-- SIDEBAR -->
    <div class="bg-dark text-white p-3" style="width: 250px; min-height: 100vh;">

        <h4 class="mb-4">Social Bee</h4>

        <div class="nav flex-column">

            <a href="#" class="text-white mb-2">Dashboard</a>
            <a href="#" class="text-white mb-2">Ads</a>
            <a href="#" class="text-white mb-2">CRM</a>
            <a href="#" class="text-white mb-2">Users</a>
            <a href="#" class="text-white mb-2">Settings</a>

        </div>

    </div>

    <!-- MAIN -->
    <div class="p-4 w-100">

        <h2>Dashboard</h2>

        <!-- STATS -->
        <div class="row g-3 mt-3">

            <div class="col-md-3">
                <div class="card p-3 shadow-sm">
                    <h6>Users</h6>
                    <h3>1,240</h3>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card p-3 shadow-sm">
                    <h6>Ads</h6>
                    <h3>320</h3>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card p-3 shadow-sm">
                    <h6>Leads</h6>
                    <h3>89</h3>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card p-3 shadow-sm">
                    <h6>Revenue</h6>
                    <h3>$8,450</h3>
                </div>
            </div>

        </div>

        <!-- TABLE -->
        <div class="card mt-4 p-3 shadow-sm">

            <h5>Recent Ads</h5>

            <table class="table mt-2">
                <tr>
                    <th>Title</th>
                    <th>Status</th>
                    <th>Price</th>
                </tr>

                <tr>
                    <td>iPhone 15</td>
                    <td><span class="badge bg-success">Active</span></td>
                    <td>$900</td>
                </tr>

            </table>

        </div>

    </div>

</div>

</body>
</html>