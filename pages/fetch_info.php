<?php
$connection = mysqli_connect("localhost", "root", "", "tfdb");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate and sanitize inputs
    $dataset = $_POST['dataset'];
    $rowsPerPage = isset($_POST['rowsPerPage']) ? $_POST['rowsPerPage'] : 25; // Default rows per page to 25 if not provided

    if ($dataset === 'adeno' || $dataset === 'squam' || $dataset === 'oncosg' || $dataset === 'mskcc' || $dataset === 'mskcc2020' || $dataset === 'mskcc2022') {
        $tableName = $dataset . '_mut_data'; // Determine table name based on cancer type

        // Query to fetch data from the selected table
        $query = "SELECT gene_id, gene_name, protein_sequence FROM $tableName LIMIT ?";
        $stmt = mysqli_prepare($connection, $query);
        mysqli_stmt_bind_param($stmt, "i", $rowsPerPage);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if (mysqli_num_rows($result) > 0) {
            // Display the table with fetched rows
            echo '<br>';
			echo '<h3>Mutated Transcription Factors:</h3>';
			echo '<table border=1>';
            echo '<thead><tr><th>Gene id</th><th>Gene name</th><th>Sequence Length</th></tr></thead>';
            echo '<tbody>';
            while ($row = mysqli_fetch_assoc($result)) {
                $geneId = $row['gene_id'];
                $geneName = $row['gene_name'];
                $sequence = $row['protein_sequence'];
                $sequenceLength = strlen($sequence);
                echo "<tr>";
                echo "<td>$geneId</td>";
                echo "<td>$geneName</td>";
                echo "<td>$sequenceLength</td>";
                echo "</tr>";
            }
            echo '</tbody>';
            echo '</table>';
        } else {
            echo 'No results found.';
        }

        mysqli_stmt_close($stmt);
    } else {
        echo 'Invalid cancer type.';
    }

    mysqli_close($connection);
}
?>
