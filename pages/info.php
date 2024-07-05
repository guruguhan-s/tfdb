<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lung Cancer Tf db</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
            margin: 0;
            padding: 0;
        }
        header {
            background-color: #394867;
            color: #f5f5f5;
            text-align: center;
            padding: 20px;
            padding-top: 5px;
            height: 130px;
        }
        nav {
            text-align: center;
            margin-top: 30px;
        }
        nav a {
            text-decoration: none;
            color: #f5f5f5;
            margin: 10px;
            padding: 5px 15px;
            background-color: #4568dc;
            border-radius: 5px;
            transition: background-color 0.3s ease-in-out, transform 0.2s ease-in-out;
        }
        nav a:hover {
            background-color: #5f87e7;
            transform: scale(1.05);
        }
        nav a:active {
            background-color: #3d63c6;
            transform: scale(0.95);
        }
        .container {
            max-width: 1090px;
            margin: 0 auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .form-group {
            margin-bottom: 15px;
            position: relative;
        }
        select, input[type="text"] {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
            border-radius: 5px;
            border: 1px solid #ccc;
            box-sizing: border-box;
        }
        button {
            background-color: #4568dc;
            color: #fff;
            padding: 10px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s ease-in-out, transform 0.2s ease-in-out;
        }
        button:hover {
            background-color: #5f87e7;
            transform: scale(1.05);
        }
        button:active {
            background-color: #3d63c6;
        }
        #geneDropdown {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            border: 1px solid #ccc;
            max-height: 150px;
            overflow-y: auto;
            background-color: white;
            z-index: 1;
            width: 100%;
            box-sizing: border-box;
        }
        #geneDropdown option {
            padding: 10px;
            cursor: pointer;
        }
        #geneDropdown option:hover {
            background-color: #f0f0f0;
        }
        #loading {
            display: none;
            text-align: center;
            margin-top: 20px;
        }
        table {
            margin: 0 auto; /* Center the table */
            border-collapse: collapse;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 0 5px rgba(0, 0, 0, 0.1);
            width: 100%; /* Ensure table fills its container */
            max-width: 100%; /* Limit table to not exceed its container */
        }
        table th, table td {
            padding: 10px;
            text-align: center;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #4568dc;
            color: #fff;
        }
        tbody tr:nth-child(odd) {
            background-color: #f2f2f2;
        }
    </style>
	<script src="../codes/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function(){
            $('#geneForm').on('submit', function(event) {
                event.preventDefault();
                var formData = $(this).serialize();
                var dataset = $('#dataset').val();
                var rowsPerPage = $('#rowsPerPage').val(); // Get rowsPerPage value
                $('#loading').show(); // Show loading GIF before AJAX call
                $.ajax({
                    url: 'fetch_info.php',
                    type: 'POST',
                    data: { dataset: dataset, rowsPerPage: rowsPerPage }, // Include rowsPerPage in data
                    success: function(response) {
                        $('#results').html(response);
                        $('#rowsPerPageForm').show(); // Show rows per page selector
                    },
                    error: function() {
                        $('#results').html('An error occurred.');
                    },
                    complete: function() {
                        $('#loading').hide(); // Hide loading GIF after AJAX completes
                    }
                });
            });

            $('#rowsPerPageForm').on('change', '#rowsPerPage', function() {
                var rowsPerPage = $(this).val();
                var dataset = $('#dataset').val();
                $('#loading').show(); // Show loading GIF before AJAX call
                $.ajax({
                    url: 'fetch_info.php',
                    type: 'POST',
                    data: { dataset: dataset, rowsPerPage: rowsPerPage },
                    success: function(response) {
                        $('#results').html(response);
                    },
                    error: function() {
                        $('#results').html('An error occurred.');
                    },
                    complete: function() {
                        $('#loading').hide(); // Hide loading GIF after AJAX completes
                    }
                });
            });
        });
    </script>
</head>
<body>
<header>
    <h1>Lung Cancer Database</h1>
    <nav>
        <a href="../index.html">Home</a>
        <a href="info.php">Information</a>
        <a href="protein_seq.php">Protein Sequence</a>
        <a href="mutations.php">Mutation information</a>
		<a href="expr.php">Expression comparison</a>
        <a href="surv.php">Survival analysis</a>
        <a href="download.php">Download</a>
        <a href="contact.html">Contact Us</a>
    </nav>
</header>
<div class="container">
    <form id="geneForm">
        <div class="form-group">
            <label for="dataset">Choose Dataset:</label>
            <select id="dataset" name="dataset">
                <option value="">Select</option>
                <option value="adeno">Lung Adenocarcinoma (TCGA, PanCancer Atlas)</option>
                <option value="squam">Lung Squamous Cell Carcinoma (TCGA, PanCancer Atlas)</option>
				<option value="oncosg">Lung Adenocarcinoma (OncoSG, Nat Genet 2020)</option>
				<option value="mskcc">Lung Adenocarcinoma Met Organotropism (MSK, Cancer Cell 2023)</option>
				<option value="mskcc2020">Lung Adenocarcinoma (MSK, J Thorac Oncol 2020)</option>
				<option value="mskcc2022">Metastatic Non-Small Cell Lung Cancer (MSK, Nature Medicine 2022)</option>
            </select>
        </div><br>
        <button type="submit">Submit</button>
        <div id="loading"><img src="../files/loading.gif" alt="Loading..." height="75px"></div>
    </form><br><br>
    <div id="rowsPerPageForm" style="display: none;">
        <label for="rowsPerPage">Rows per page:</label>
        <select id="rowsPerPage" name="rowsPerPage">
            <option value="25">25</option>
            <option value="50">50</option>
            <option value="100">100</option>
            <option value="1303">All entries</option>
        </select>
    </div><br>
    <div id="results"></div>
</div>
</body>
</html>
