<?php  

// Modules array
$modules = array(
    'pagespeed' => 'PageSpeed',
    'search_console' => 'Search Console',
    'wappalyzer' => 'Wappalyzer',
    'search_index' => 'Search Index',
    'ahrefs' => 'Ahrefs',
);

// Test websites
$test_websites = array(
    'https://example.com',
);

 
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>DRA Sale Reports</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-rbsA2VBKQhggwzxH7pPCaAqO46MgnOM80zW1RWuH61DGLwZJEdK2Kadq2F9CUG65" crossorigin="anonymous">
</head>
<body>
    <nav class="navbar navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">Sales Reports</a>
        </div>
    </nav>

    <main class="container my-5">
        <section class="mb-5">  
            <h2 class="mb-4">Generate Reports</h2>
            <form id="reports" method="post">
                <div class="mb-4">
                    <label for="spreadsheet_url" class="form-label">Spreadsheet URL</label>
                    <input type="text" class="form-control" id="spreadsheet_url" name="spreadsheet_url" value="https://docs.google.com/spreadsheets/d/18FcC4Dqkb_qf96K1P7EgS4gZgeaaLHe9ltRvnkRZ-rc/edit#gid=0">
                </div>
                <div class="mb-4 row">
                    <div class="col">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="debug_mode" id="debug_mode" checked>
                            <label class="form-check-label" for="debug_mode">Debug Mode ( Use Test Data. Do not Sync Spreadsheet ) </label>
                        </div>
                    </div>
                    <div class="col">
                        <label for="test_data" class="form-label">Test Data</label>
                        <textarea class="form-control" id="test_data" name="test_data" rows="6"><?php echo join("\n", $test_websites); ?></textarea>
                    </div>
                </div>
                <!-- <div class="mb-4 row">
                    <div class="col">
                        <label for="start_offset" class="form-label">Start offset</label>
                        <input type="number" class="form-control" id="start_offset" name="start_offset" value="0">
                    </div>
                    <div class="col">
                        <label for="total_rows" class="form-label">End offset</label>
                        <input type="number" class="form-control" id="end_offset" name="end_offset">
                    </div>
                </div> -->
                <div class="mb-4">
                    <label for="total_rows" class="form-label">Batch Size</label>
                    <input type="number" class="form-control" id="batch_size" name="batch_size" value="5">
                </div>

                <div class="mb-4">
                    <p>Modules to include</p>
                    <?php foreach ($modules as $module => $label) : ?>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="modules[]" value="<?php echo $module; ?>" id="module-<?php echo $module; ?>" checked>
                        <label class="form-check-label" for="module-<?php echo $module; ?>"><?php echo $label; ?></label>
                    </div>
                    <?php endforeach; ?>
                </div>
                <button id="startButton" type="submit" class="btn btn-primary">Start</button>
                <button id="stopButton" type="button" class="btn btn-danger" disabled>Stop</button>
            </form>
        </section>

        <section>
            <h2 class="mb-4">Log</h2>
            <div id="log" class="text-bg-light border p-4 rounded small"></div>
        </section>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-kenU1KFdBIe4zVF0s0G1M5b4hcpxyD9F7jL+jjXkk+Q2h455rYXK/7HAuoJl+0I4" crossorigin="anonymous"></script>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>

    <script>
        const messageColors = {
            'DEBUG': 'dark',
            'ERROR': 'danger',
            'WARNING': 'primary',
            'INFO': 'success',
        };
        let logInterval = null;
        const batchXhr = [];

        $(document).ready(function() {
            $('#reports').on('submit', startBatch);
            $('#stopButton').on('click', stopBatch);
        });

        const stopBatch = (e) => {
            // Cancel batch processing ajax call
            cleanup()
            if (batchXhr.length) {
                batchXhr.forEach(xhr => xhr.abort());
            }
            $('#log').append(`<div class="text-danger">Progress stopped.</div>`);
        }

        const startBatch = (e) => {
            e.preventDefault();

            console.log('Start Batch');

            // Get all input values from the form
            const data = $('#reports').serializeArray();

            // Empty log
            $('#log').empty();

            // Disable start button and enable stop button.
            $('#startButton').prop('disabled', true);
            $('#stopButton').prop('disabled', false);

            // Send the data to the server and get response in json format.
            const xhr = $.ajax({
                url: '/src/ajax.php?start=true',
                method: 'POST',
                data: data,
                dataType: 'JSON',
            }).done(function({logs}) {
                handleResponse(logs)

                // Start processing batch if success
                processBatch()

                // // Start fetching logs
                // logInterval = setInterval(fetchLogs, 3000);
            }).fail(function(response) {
                console.log('Fail');
                console.log('response', response)
                if (response.responseJSON) {
                    handleResponse(response.responseJSON.logs)
                }
                cleanup()
            })

            batchXhr.push(xhr);
        }

        const processBatch = () => {
            const xhr = $.ajax({
                url: '/src/ajax.php?process_batch=true',
                dataType: 'JSON',
            }).done(function({data, logs}) {
                handleResponse(logs);

                if (data.continueProcessing) {
                    processBatch();
                } else {
                    cleanup();
                }
            }).fail(function(response) {
                console.log('Fail');
                console.log('response', response)
                if (response.responseJSON) {
                    handleResponse(response.responseJSON.logs)
                }
                cleanup()
            });

            batchXhr.push(xhr);
        }

        const fetchLogs = () => {
            // return;
            const xhr = $.ajax({
                url: '/src/ajax.php?fetch_logs=true',
                dataType: 'JSON',
            }).done(function({logs}) {
                handleResponse(logs);
            });

            batchXhr.push(xhr);
        }

        const cleanup = () => {
            $('#startButton').prop('disabled', false);
            $('#stopButton').prop('disabled', true);
            clearInterval(logInterval);
        }

        const handleResponse = (logs) => {
            try {
                logs && logs.forEach(({time, message, type}) => {
                    $('#log').append(`<div class="text-${messageColors[type]}"><span>${time}</span> ${type}: ${message}</div>`);
                });
            } catch (error) {
                cleanup();
                $('#log').append(`<div class="text-danger">${error}</div>`);
            }
        }
    </script>
</body>
</html>
