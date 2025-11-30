<?php
ob_start();
session_start();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" integrity="sha384-xOolHFLEh07PJGoPkLv1IbcEPTNtaed2xpHsD9ESMhqIYd0nLMwNLD69Npy4HI+N" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.16/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/select/1.2.5/css/select.dataTables.min.css">
    <link rel="stylesheet" href="css/main.css">
    <?php

    require 'lib/phpPasswordHashing/passwordLib.php';
    require 'app/DB.php';
    require 'app/Util.php';
    require 'app/models/StatusEnum.php';
    require 'app/models/RequirementEnum.php';
    require 'app/dao/CustomerDAO.php';
    require 'app/dao/BookingDetailDAO.php';
    require 'app/models/Customer.php';
    require 'app/models/Booking.php';
    require 'app/models/Reservation.php';
    require 'app/handlers/CustomerHandler.php';
    require 'app/handlers/BookingDetailHandler.php';

    $username = null;
    $isSessionExists = $isAdmin = false;
    $pendingReservation = $confirmedReservation = $totalCustomers = $totalReservations = null;
    $allBookings = $cCommon = $allCustomer = null;
    if (isset($_SESSION["username"]))
    {
        $username = $_SESSION["username"];
        $isSessionExists = true;

        $cHandler = new CustomerHandler();
        $cHandler = $cHandler->getCustomerObj($_SESSION["accountEmail"]);

        $cAdmin = new Customer();
        $cAdmin->setEmail($cHandler->getEmail());

        // display all reservations
        $bdHandler = new BookingDetailHandler();
        $allBookings = $bdHandler->getAllBookings();
        $cCommon = new CustomerHandler();
        $allCustomer = $cCommon->getAllCustomer();

        // reservation stats
        $pendingReservation = $bdHandler->getPending();
        $confirmedReservation = $bdHandler->getConfirmed();
        $totalCustomers = $cCommon->totalCustomersCount();
        $totalReservations = count($bdHandler->getAllBookings());

        // additional stats: bookings by type and recent totals
        $singleCount = 0;
        $doubleCount = 0;
        $weekCount = 0; // last 7 days
        $monthCount = 0; // current month
        $now = new DateTime();
        $sevenDaysAgo = (clone $now)->modify('-7 days');
        $currentMonth = (int)$now->format('n');
        $currentYear = (int)$now->format('Y');

        if (!empty($allBookings) && is_array($allBookings)) {
            foreach ($allBookings as $b) {
                // type count (case-insensitive)
                $type = isset($b['type']) ? strtolower(trim($b['type'])) : '';
                if ($type === 'single') {
                    $singleCount++;
                } elseif ($type === 'double') {
                    $doubleCount++;
                }

                // timestamp parse and recent counts
                $ts = isset($b['timestamp']) ? $b['timestamp'] : null;
                if ($ts) {
                    try {
                        $d = new DateTime($ts);
                        if ($d >= $sevenDaysAgo && $d <= $now) {
                            $weekCount++;
                        }
                        if ((int)$d->format('n') === $currentMonth && (int)$d->format('Y') === $currentYear) {
                            $monthCount++;
                        }
                    } catch (Exception $e) {
                        // ignore parse errors
                    }
                }
            }
        }
    }
    if (isset($_SESSION["isAdmin"]) && isset($_SESSION["username"])) {
        $isSessionExists = true;
        $username = $_SESSION["username"];
        $isAdmin = $_SESSION["isAdmin"];
    }

    ?>

    <title>Manage Booking</title>
</head>
<body>

<header>
    <div class="bg-dark collapse" id="navbarHeader" style="">
        <div class="container">
            <div class="row">
                <div class="col-sm-8 col-md-7 py-4">
                    <h4 class="text-white">About</h4>
                    <p class="text-muted">Add some information about hotel booking.</p>
                </div>
                <div class="col-sm-4 offset-md-1 py-4 text-right">
                    <!-- User full name or email if logged in -->
                    <?php if ($isSessionExists) { ?>
                    <h4 class="text-white"><?php echo $username; ?></h4>
                    <ul class="list-unstyled">
                        <li><a href="index.php" class="text-white">Home</a><i class="fas fa-home ml-2"></i></a></li>
                        <li><a href="#" id="sign-out-link" class="text-white">Sign out<i class="fas fa-sign-out-alt ml-2"></i></a></li>
                    </ul>
                    <?php } else { ?>
                    <h4>
                        <a class="text-white" href="sign-in.php">Sign in</a> <span class="text-white">or</span>
                        <a href="register.php" class="text-white">Register </a>
                    </h4>
                    <p class="text-muted">Log in so you can take advantage with our hotel room prices.</p>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
    <div class="navbar navbar-dark bg-dark box-shadow">
        <div class="container d-flex justify-content-between">
            <a href="#" class="navbar-brand d-flex align-items-center">
                <i class="fas fa-h-square mr-2"></i>
                <strong>Hotel Booking</strong>
            </a>
            <button class="navbar-toggler collapsed" type="button" data-toggle="collapse" data-target="#navbarHeader" aria-controls="navbarHeader" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
        </div>
    </div>
</header>

<main role="main">

    <?php if ($isSessionExists && $isAdmin) { ?>
    <div class="container my-3">
        <div class="row">
            <div class="col-xl-3 col-sm-6 mb-3">
                <div class="card text-white bg-primary o-hidden h-100">
                    <div class="card-body">
                        <div class="card-body-icon">
                            <i class="fas fa-address-book"></i>
                        </div>
                        <div class="mr-5"><?php echo $totalReservations; ?> Reservations</div>
                    </div>
                    <a class="card-footer text-white clearfix small z-1" href="#reservation">
                        <span class="float-left">View Details</span>
                        <span class="float-right"><i class="fa fa-angle-right"></i></span>
                    </a>
                </div>
            </div>
            <div class="col-xl-3 col-sm-6 mb-3">
                <div class="card text-white bg-warning o-hidden h-100">
                    <div class="card-body">
                        <div class="card-body-icon">
                            <i class="fas fa-users ml-2"></i>
                        </div>
                        <div class="mr-5"><?php echo $totalCustomers; ?> Customers</div>
                    </div>
                    <a class="card-footer text-white clearfix small z-1" href="#customers">
                        <span class="float-left">View Details</span>
                        <span class="float-right"><i class="fa fa-angle-right"></i></span>
                    </a>
                </div>
            </div>
            <div class="col-xl-3 col-sm-6 mb-3">
                <div class="card text-white bg-success o-hidden h-100">
                    <div class="card-body">
                        <div class="card-body-icon">
                            <i class="fas fa-check"></i>
                        </div>
                        <div class="mr-4"><?php echo $confirmedReservation; ?> Confirmed Reservations</div>
                    </div>
                    <div class="card-footer text-white clearfix small z-1"></div>
                </div>
            </div>
            <div class="col-xl-3 col-sm-6 mb-3">
                <div class="card text-white bg-danger o-hidden h-100">
                    <div class="card-body">
                        <div class="card-body-icon">
                            <i class="fa fa-fw fa-support"></i>
                        </div>
                        <div class="mr-5"><?php echo $pendingReservation; ?> Pending Reservations</div>
                    </div>
                    <div class="card-footer text-white clearfix small z-1"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="container" id="tableContainer">
        <ul class="nav nav-tabs" id="adminTab" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" id="reservation-tab" data-toggle="tab" href="#reservation" role="tab"
                   aria-controls="reservation" aria-selected="true">Reservation</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="customers-tab" data-toggle="tab" href="#customers" role="tab"
                   aria-controls="customers" aria-selected="false">Customers</a>
            </li>
        </ul>
        <div class="tab-content py-3" id="adminTabContent">
            <div class="tab-pane fade show active" id="reservation" role="tabpanel" aria-labelledby="reservation-tab">
                <div class="d-flex justify-content-end mb-2">
                    <button id="print-reservation" class="btn btn-outline-secondary btn-sm">Print Reservation Report</button>
                </div>
                <div id="reservationSummary" class="mb-3">
                    <div class="row text-center">
                        <div class="col-sm-6 col-md-3 mb-2">
                            <div class="card bg-primary text-white">
                                <div class="card-body py-2">
                                    <div class="h6 mb-1">Total Reservations</div>
                                    <div class="h4 font-weight-bold"><?php echo isset($totalReservations) ? $totalReservations : 0; ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-3 mb-2">
                            <div class="card bg-success text-white">
                                <div class="card-body py-2">
                                    <div class="h6 mb-1">Confirmed</div>
                                    <div class="h4 font-weight-bold"><?php echo isset($confirmedReservation) ? $confirmedReservation : 0; ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-3 mb-2">
                            <div class="card bg-danger text-white">
                                <div class="card-body py-2">
                                    <div class="h6 mb-1">Pending</div>
                                    <div class="h4 font-weight-bold"><?php echo isset($pendingReservation) ? $pendingReservation : 0; ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-3 mb-2">
                            <div class="card bg-info text-white">
                                <div class="card-body py-2">
                                    <div class="h6 mb-1">This Month</div>
                                    <div class="h4 font-weight-bold"><?php echo isset($monthCount) ? $monthCount : 0; ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row text-center mt-2">
                        <div class="col-sm-6 col-md-3 mb-2">
                            <div class="card bg-secondary text-white">
                                <div class="card-body py-2">
                                    <div class="h6 mb-1">Last 7 days</div>
                                    <div class="h4 font-weight-bold"><?php echo isset($weekCount) ? $weekCount : 0; ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-3 mb-2">
                            <div class="card" style="background:linear-gradient(90deg,#6f42c1,#007bff); color:#fff;">
                                <div class="card-body py-2">
                                    <div class="h6 mb-1">Single bookings</div>
                                    <div class="h4 font-weight-bold"><?php echo isset($singleCount) ? $singleCount : 0; ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-3 mb-2">
                            <div class="card" style="background:linear-gradient(90deg,#ff7e5f,#feb47b); color:#fff;">
                                <div class="card-body py-2">
                                    <div class="h6 mb-1">Double bookings</div>
                                    <div class="h4 font-weight-bold"><?php echo isset($doubleCount) ? $doubleCount : 0; ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-3 mb-2">
                            <div class="card bg-light">
                                <div class="card-body py-2">
                                    <div class="h6 mb-1">&nbsp;</div>
                                    <div class="h4 font-weight-bold">&nbsp;</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <table id="reservationDataTable" class="table table-striped table-bordered" cellspacing="0" width="100%">
                    <thead>
                    <tr>
                        <th scope="col">#</th>
                        <th class="text-hide p-0" data-bookId="12">12</th>
                        <th scope="col">Email</th>
                        <th scope="col">Start</th>
                        <th scope="col">End</th>
                        <th scope="col">Room type</th>
                        <th scope="col">Timestamp</th>
                        <th scope="col">Status</th>
                        <th scope="col">Notes</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($allBookings)) { ?>
                        <?php   foreach ($allBookings as $k => $v) { ?>
                            <tr>
                                <th scope="row"><?php echo ($k + 1); ?></th>
                                <td class="text-hide p-0" data-id="<?php echo $v["id"]; ?>">
                                    <?php echo $v["id"]; ?>
                                </td>
                                <?php $cid = $v["cid"]; ?>
                                <td><?php echo $cCommon->getCustomerObjByCid($cid)->getEmail(); ?></td>
                                <td><?php echo $v["start"]; ?></td>
                                <td><?php echo $v["end"]; ?></td>
                                <td><?php echo $v["type"]; ?></td>
                                <td><?php echo $v["timestamp"]; ?></td>
                                <td><?php echo $v["status"]; ?></td>
                                <td><?php echo $v["notes"]; ?></td>
                            </tr>
                        <?php } ?>
                    <?php } ?>
                    </tbody>
                </table>
                <div class="my-3">
                    <div class="row">
                        <div class="col-6">
                            <label class="text-secondary font-weight-bold">With selected:</label>
                            <button type="button" id="confirm-booking" class="btn btn-outline-success btn-sm">Confirm
                            </button>
                            <button type="button" id="cancel-booking" class="btn btn-outline-danger btn-sm">Cancel
                            </button>
                        </div>
                        <div class="col-6 text-right">
                            View:
                            <input type="radio" name="viewOption" value="confirmed">&nbsp;Confirmed&nbsp;
                            <input type="radio" name="viewOption" value="pending">&nbsp;Pending
                            <input type="radio" name="viewOption" value="all">&nbsp;All
                        </div>
                    </div>
                </div>
            </div>
            <div class="tab-pane fade" id="customers" role="tabpanel" aria-labelledby="customers-tab">
                <div class="d-flex justify-content-end mb-2">
                    <button id="print-customers" class="btn btn-outline-secondary btn-sm">Print Customers Report</button>
                </div>
                <div id="customersSummary" class="mb-3">
                    <div class="row text-center">
                        <div class="col-sm-6 col-md-4 mb-2">
                            <div class="card bg-warning text-white">
                                <div class="card-body py-2">
                                    <div class="h6 mb-1">Total Customers</div>
                                    <div class="h4 font-weight-bold"><?php echo isset($totalCustomers) ? $totalCustomers : 0; ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-4 mb-2">
                            <div class="card bg-light">
                                <div class="card-body py-2">
                                    <div class="h6 mb-1">&nbsp;</div>
                                    <div class="h4 font-weight-bold">&nbsp;</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-4 mb-2">
                            <div class="card bg-light">
                                <div class="card-body py-2">
                                    <div class="h6 mb-1">&nbsp;</div>
                                    <div class="h4 font-weight-bold">&nbsp;</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <table id="customerTable" class="table table-bordered">
                    <thead class="thead-dark">
                    <tr>
                        <th scope="col">#</th>
                        <th scope="col">Full name</th>
                        <th scope="col">Email</th>
                        <th scope="col">Phone</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($allCustomer)) { ?>
                        <?php foreach ($cCommon->getAllCustomer() as $key => $value) { ?>
                        <tr>
                            <td scope="row"><?php echo ($key + 1); ?></td>
                            <td><?php echo $value->getFullName(); ?></td>
                            <td><?php echo $value->getEmail(); ?></td>
                            <td><?php echo $value->getPhone(); ?></td>
                        </tr>
                        <?php } ?>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <div class="modal fade" id="confirmModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm selected reservation(s)</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to proceed with this action?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" id="confirmTrue">Yes</button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">No</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="cancelModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Cancel selected reservation(s)</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to proceed with this action?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" id="cancelTrue">Yes</button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">No</button>
                </div>
            </div>
        </div>
    </div>

    <?php } ?>

</main>

<footer class="container">
    <p>&copy; Company 2017-2018</p>
</footer>

<!-- jQuery first, then Popper.js, then Bootstrap JS -->
<script src="https://code.jquery.com/jquery-3.6.3.min.js" integrity="sha256-pvPw+upLPUjgMXY0G+8O0xUf+/Im1MZjXxxgOcBQBXU=" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js" integrity="sha384-9/reFTGAW83EW2RDu2S0VKaIzap3H66lZH81PoYlFhbGU+6BZp6G7niu735Sk7lN" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.min.js" integrity="sha384-+sLIOodYLS7CIrQpBjl+C7nPvqq+FbNUBDunl/OZv93DB7Ln/533i8e/mZXLi/P+" crossorigin="anonymous"></script>

<script defer src="https://use.fontawesome.com/releases/v5.0.10/js/all.js"
        integrity="sha384-slN8GvtUJGnv6ca26v8EzVaR9DC58QEwsIk9q1QXdCU8Yu8ck/tL/5szYlBbqmS+"
        crossorigin="anonymous"></script>
<script src="https://cdn.datatables.net/1.10.16/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/select/1.2.5/js/dataTables.select.min.js"></script>
<script src="js/form-submission.js"></script>
<script src="js/admin.js"></script>

<script>
  // Print button handlers
  document.addEventListener('DOMContentLoaded', function () {
    // Print Reservation Report
    var printReservationBtn = document.getElementById('print-reservation');
    if (printReservationBtn) {
      printReservationBtn.addEventListener('click', function () {
        printTable('reservationReport');
      });
    }
    
    // Print Customers Report
    var printCustomersBtn = document.getElementById('print-customers');
    if (printCustomersBtn) {
      printCustomersBtn.addEventListener('click', function () {
        printTable('customersReport');
      });
    }
  });
  
    // Function to print specific report (includes summary + full data)
    function printTable(reportType) {
        var printWindow = window.open('', '', 'height=900,width=1100');
        var content = '';
        var title = '';
        var table = '';
        var summaryHtml = '';

        if (reportType === 'reservationReport') {
            title = 'Reservation Report';
            var tbl = document.getElementById('reservationDataTable');
            table = tbl ? tbl.outerHTML : '<p>No reservation data available.</p>';
            var sum = document.getElementById('reservationSummary');
            summaryHtml = sum ? sum.innerHTML : '';
        } else if (reportType === 'customersReport') {
            title = 'Customers Report';
            var ctbl = document.getElementById('customerTable');
            table = ctbl ? ctbl.outerHTML : '<p>No customer data available.</p>';
            var csum = document.getElementById('customersSummary');
            summaryHtml = csum ? csum.innerHTML : '';
        }

        var today = new Date().toLocaleString();
        // Build a clean, print-friendly HTML with repeating table headers and clear summary
        content = `
            <html>
                <head>
                    <title>${title}</title>
                    <meta charset="utf-8" />
                    <style>
                        @media print {
                            @page { margin: 18mm; }
                            body { -webkit-print-color-adjust: exact; }
                        }
                        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial; color: #222; margin: 12px; }
                        .header { display:flex; align-items:center; gap:12px; }
                        .logo { width:48px; height:48px; object-fit:contain; }
                        .company { font-size:20px; font-weight:700; }
                        .address { font-size:12px; color:#666; }
                        .report-title { text-align:center; margin-top:10px; margin-bottom:6px; font-size:18px; font-weight:700; }
                        .report-meta { text-align:center; font-size:12px; color:#666; margin-bottom:12px; }
                        .summary-card { border:1px solid #e0e0e0; padding:12px; border-radius:6px; background:#fafafa; max-width:720px; margin:0 auto 12px auto; }
                        .summary-row { display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px dashed #eee; }
                        .summary-row:last-child { border-bottom: none; }
                        table { width:100%; border-collapse:collapse; margin-top:14px; font-size:12px; }
                        thead th { background:#f1f1f1; padding:8px; border:1px solid #ddd; }
                        tbody td { padding:8px; border:1px solid #ddd; }
                        tbody tr:nth-child(even) { background:#fcfcfc; }
                        /* Make sure table headers repeat on each printed page */
                        thead { display: table-header-group; }
                        tfoot { display: table-footer-group; }
                        tr { page-break-inside: avoid; }
                        .footer-note { margin-top:18px; font-size:11px; color:#666; text-align:center; }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <img class="logo" src="image/favicon/favicon-32x32.png" alt="logo" onerror="this.style.display='none'" />
                        <div>
                            <div class="company">Hotel Booking</div>
                            <div class="address">Address: Your Hotel Address — Contact: (000) 000-0000</div>
                        </div>
                    </div>
                    <div class="report-title">${title}</div>
                    <div class="report-meta">Generated on: ${today}</div>
                    <div class="summary-card">
                        ${summaryHtml}
                    </div>
                    ${table}
                    <div class="footer-note">This is a system generated report. Page <span class="pageNumber"></span></div>
                    <script>
                        // attempt to print and close; let the user cancel if needed
                        window.print();
                        window.onafterprint = function() { try { window.close(); } catch(e){} };
                    <\/script>
                </body>
            </html>
        `;

        printWindow.document.write(content);
        printWindow.document.close();
    }
</script>
</body>
</html>