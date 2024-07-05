<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Survival</title>
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
            max-width: 1200px;
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
        .plot-container {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-around;
        }
        .plot {
            width: 45%;
            margin: 20px 0;
        }
        .plot img {
            max-width: 100%;
            height: auto;
            border: 1px solid #ccc;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
    </style>
<script src="../codes/jquery-3.6.0.min.js"></script>
<script>
    $(document).ready(function(){
        function fetchGenes(dataset) {
            $.ajax({
                url: 'fetch_genes.php',
                type: 'POST',
                data: { dataset: dataset },
                success: function(response) {
                    let geneList = JSON.parse(response);
                    $('#geneName').data('geneList', geneList);
                },
                error: function() {
                    console.error('Error fetching genes.');
                }
            });
        }

        $('#dataset').change(function(){
            let dataset = $(this).val();
            if (dataset) {
                fetchGenes(dataset);
            }
        });

        function matchGene(input, geneList) {
            return geneList.filter(gene => gene.toLowerCase().includes(input.toLowerCase()));
        }

        $('#geneName').on('input', function(){
            let inputVal = $(this).val();
            let geneList = $(this).data('geneList') || [];
            let matches = matchGene(inputVal, geneList);
            let dropdown = $('#geneDropdown');
            dropdown.empty();
            matches.forEach(match => {
                dropdown.append('<option value="' + match + '">' + match + '</option>');
            });
            if (matches.length > 0) {
                dropdown.show();
            } else {
                dropdown.hide();
            }
        });

        $('#geneDropdown').on('click', function() {
            var selectedValue = $(this).val();
            console.log('Selected Value:', selectedValue); // Debugging: log the selected value
            $('#geneName').val(selectedValue); // Update geneName input with selected value
            $('#geneDropdown').hide(); // Hide the dropdown after selection
        });

        $('#geneForm').on('submit', function(event) {
            event.preventDefault();
            $('#loading').show();
            $.ajax({
                url: 'expr_plot.php',
                type: 'POST',
                data: $(this).serialize(),
                success: function(response) {
                    $('#results').html(response);
                    $('#loading').hide();
                },
                error: function() {
                    $('#results').html('An error occurred.');
                    $('#loading').hide();
                }
            });
        });

        $('#geneName').focus(function(){
            let inputVal = $(this).val();
            let geneList = $(this).data('geneList') || [];
            if (inputVal && geneList.length > 0) {
                $('#geneDropdown').show();
            }
        });

        $('#geneName').blur(function(){
            if (!$(this).is(":focus")) {
                setTimeout(function() {
                    $('#geneDropdown').hide();
                }, 200);
            }
        });

        $('#geneDropdown').on('click', 'option', function(){
            var selectedValue = $(this).val();
            $('#geneName').val(selectedValue); // Update geneName input with selected value
            $('#geneDropdown').hide(); // Hide the dropdown after selection
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
				</select>
            </div>
            <div class="form-group">
                <label for="geneName">Enter TF Symbol:</label>
                <input type="text" id="geneName" name="geneName" autocomplete="off">
                <select id="geneDropdown" size="5"></select>
            </div>
            <br><button type="submit">Submit</button>
        </form>
        <div id="loading"><img src="../files/loading.gif" alt="Loading..." height="75px"></div>
        <br>
        <div id="results"></div>
    </div>
</body>
</html>