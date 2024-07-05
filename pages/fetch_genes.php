<?php
// Database connection details
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "tfdb";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$dataset = $_POST['dataset'];
$gene_ids = array();

if ($dataset == 'adeno') {
    $sql1 = "SELECT gene_id FROM adeno_mut_data";
    $sql2 = "SELECT gene_id FROM adeno_cna_data";
} elseif ($dataset == 'squam') {
    $sql1 = "SELECT gene_id FROM squam_mut_data";
    $sql2 = "SELECT gene_id FROM squam_cna_data";
} elseif ($dataset == 'oncosg') {
    $sql1 = "SELECT gene_id FROM oncosg_mut_data";
    $sql2 = "SELECT gene_id FROM oncosg_cna_data";
} elseif ($dataset == 'mskcc') {
    $sql1 = "SELECT gene_id FROM mskcc_mut_data";
	$sql2 = "SELECT gene_id FROM mskcc_mut_data";
}
 elseif ($dataset == 'mskcc2020') {
    $sql1 = "SELECT gene_id FROM mskcc2020_mut_data";
	$sql2 = "SELECT gene_id FROM mskcc2020_cna_data";
}
 elseif ($dataset == 'mskcc2022') {
    $sql1 = "SELECT gene_id FROM mskcc2022_mut_data";
	$sql2 = "SELECT gene_id FROM mskcc2022_cna_data";
}

$result1 = $conn->query($sql1);
$result2 = $conn->query($sql2);

if ($result1->num_rows > 0) {
    while ($row = $result1->fetch_assoc()) {
        $gene_ids[] = $row['gene_id'];
    }
}
if ($result2->num_rows > 0) {
    while ($row = $result2->fetch_assoc()) {
        $gene_ids[] = $row['gene_id'];
    }
}
$conn->close();

// Remove duplicates and encode as JSON
$gene_ids = array_unique($gene_ids);
echo json_encode(array_values($gene_ids));
?>