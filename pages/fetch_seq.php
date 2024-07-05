<?php
$con = mysqli_connect("localhost", "root", "", "tfdb");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate and sanitize inputs
    $dataset = $_POST['dataset'];
    $rowsPerPage = isset($_POST['rowsPerPage']) ? intval($_POST['rowsPerPage']) : 25;
    $limit = $rowsPerPage > 0 ? "LIMIT $rowsPerPage" : "";

    if ($dataset === 'adeno' || $dataset === 'squam' || $dataset === 'oncosg' || $dataset === 'mskcc' || $dataset === 'mskcc2020' || $dataset === 'mskcc2022') {
        // Determine table name based on cancer type
        $tableName = $dataset . '_mut_data';

        // Query to fetch protein sequences with gene_id and protein_sequence columns
        $sql = "SELECT gene_id, protein_sequence FROM $tableName $limit";
        $result = mysqli_query($con, $sql);

        if ($result) {
            $count = 1;
            while ($row = mysqli_fetch_assoc($result)) {
                echo "<tr>";
                echo "<td>" . $count++ . "</td>";
                echo "<td>" . $row['gene_id'] . "</td>";
                echo "<td class='max-width-td'>";
                echo "<div class='sequence-container'>";
                echo "<span class='sequence-text'>" . $row['protein_sequence'] . "</span>";
                echo "<button class='copy-button' onclick='copySequence(this)'>Copy</button>";
                echo "</div>";
                echo "</td>";
                echo "</tr>";
            }
        } else {
            echo "<tr><td colspan='3'>No results found.</td></tr>";
        }
    } else {
        echo "<tr><td colspan='3'>Invalid cancer type.</td></tr>";
    }

    mysqli_close($con);
}
?>
