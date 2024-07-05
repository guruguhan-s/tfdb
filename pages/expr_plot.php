<?php
set_time_limit(10); // Set maximum execution time to 10 seconds

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "tfdb";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

ini_set('memory_limit', '900M');

// Fetch POST data and validate it
$dataset = isset($_POST['dataset']) ? $_POST['dataset'] : '';
$geneName = isset($_POST['geneName']) ? $_POST['geneName'] : '';

if (empty($dataset) || empty($geneName)) {
    die("All input fields are required.");
}

// Determine the tables based on cancer type
switch ($dataset) {
    case 'adeno':
        $rnaTable = 'adeno_rna_seq';
        $normalTable = 'adeno_rna_seq_normal';
        break;
    case 'squam':
        $rnaTable = 'squam_rna_seq';
        $normalTable = 'squam_rna_seq_normal';
        break;
}

// Fetch gene expression data
$sql = "SELECT * FROM $rnaTable WHERE Hugo_Symbol = '$geneName'";
$result = $conn->query($sql);

if ($result->num_rows == 0) {
    die("No data found for the specified gene in cancer table.");
}

$cancerData = $result->fetch_assoc();
unset($cancerData['Hugo_Symbol']); // Remove gene symbol column

$sql = "SELECT * FROM $normalTable WHERE Hugo_Symbol = '$geneName'";
$result = $conn->query($sql);

if ($result->num_rows == 0) {
    die("No data found for the specified gene in normal table.");
}

$normalData = $result->fetch_assoc();
unset($normalData['Hugo_Symbol']); // Remove gene symbol column

// Prepare data for R script
$dataFile = tempnam(sys_get_temp_dir(), 'data_') . '.csv';
$file = fopen($dataFile, 'w');
fputcsv($file, array('type', 'sample', 'expression'));
foreach ($cancerData as $sample => $expression) {
    fputcsv($file, array('Cancer', $sample, $expression));
}
foreach ($normalData as $sample => $expression) {
    fputcsv($file, array('Normal', $sample, $expression));
}
fclose($file);

// Count samples
$cancerCount = count($cancerData);
$normalCount = count($normalData);

// Path to R script
$rScript = tempnam(sys_get_temp_dir(), 'plot_expr_') . '.R';
$rCode = "
args <- commandArgs(trailingOnly = TRUE)
data_file <- args[1]
violin_image <- args[2]
box_image <- args[3]
cancer_count <- as.numeric(args[4])
normal_count <- as.numeric(args[5])

library(ggplot2)

data <- read.csv(data_file)

# Perform t-test
t_result <- t.test(expression ~ type, data = data)
p_value <- t_result\$p.value

# Calculate fold change
mean_tumor <- mean(data\$expression[data\$type == 'Cancer'])
mean_normal <- mean(data\$expression[data\$type == 'Normal'])
fold_change <- mean_tumor / mean_normal

# Update legend labels with sample counts
cancer_label <- paste0('Tumor (n=', cancer_count, ')')
normal_label <- paste0('Normal (n=', normal_count, ')')
legend_labels <- c('Cancer' = cancer_label, 'Normal' = normal_label)

# Violin Plot
violin_plot <- ggplot(data, aes(x = type, y = expression, fill = type)) + 
  geom_violin(trim = FALSE, alpha = 0.7) +
  scale_fill_manual(values = c('#FF9999', '#66CCCC'), labels = legend_labels) +  
  labs(x = NULL, y = 'Expression (RSEM)', fill = 'Type') +  # Removed p-value from main title
  theme_minimal() +
  theme(legend.position = 'bottom', legend.title = element_text(size = 10), legend.text = element_text(size = 8),
        axis.text.x = element_blank(), axis.text.y = element_text(size = 10), 
        axis.title.x = element_text(size = 12), axis.title.y = element_text(size = 12),
        plot.title = element_text(hjust = 0.5, size = 16))  # Adjusted main title size

# Annotate with p-value and fold change
violin_plot <- violin_plot +
  annotate('text', x = Inf, y = -Inf, label = paste('T-test p-value:', round(p_value, 3)),
           hjust = 1.1, vjust = -28, size = 3.5, color = 'black') +
  annotate('text', x = Inf, y = -Inf, label = paste('Fold Change:', round(fold_change, 2)),
           hjust = 1.1, vjust = -26, size = 3.5, color = 'black')

ggsave(violin_image, plot = violin_plot, width = 5.5, height = 4)

# Box Plot
box_plot <- ggplot(data, aes(x = type, y = expression, fill = type)) +
  geom_boxplot(alpha = 0.7, color = 'black', size = 0.5, position = position_dodge(width = 0.75)) +  
  scale_fill_manual(values = c('#FF9999', '#66CCCC'), labels = legend_labels) +  
  labs(x = NULL, y = 'Expression (RSEM)', fill = 'Type') +  # Removed p-value from main title
  theme_minimal() +
  theme(legend.position = 'bottom', legend.title = element_text(size = 10), legend.text = element_text(size = 8),
        axis.text.x = element_blank(), axis.text.y = element_text(size = 10), 
        axis.title.x = element_text(size = 12), axis.title.y = element_text(size = 12),
        plot.title = element_text(hjust = 0.5, size = 16))  # Adjusted main title size

# Annotate with p-value and fold change
box_plot <- box_plot +
  annotate('text', x = Inf, y = -Inf, label = paste('T-test p-value:', round(p_value, 3)),
           hjust = 1.1, vjust = -28, size = 3.5, color = 'black') +
  annotate('text', x = Inf, y = -Inf, label = paste('Fold Change:', round(fold_change, 2)),
           hjust = 1.1, vjust = -26, size = 3.5, color = 'black')

ggsave(box_image, plot = box_plot, width = 5.5, height = 4)
";
file_put_contents($rScript, $rCode);

$violinImage = tempnam(sys_get_temp_dir(), 'violin_') . '.png';
$boxImage = tempnam(sys_get_temp_dir(), 'box_') . '.png';

// Execute R script
$command = "Rscript $rScript $dataFile $violinImage $boxImage $cancerCount $normalCount";
exec($command, $output, $return_var);

if ($return_var != 0) {
    die("Error generating the plot.");
}

// Return the images
echo "<div class='plot-container'>";
echo "<div class='plot'><img src='data:image/png;base64," . base64_encode(file_get_contents($violinImage)) . "' alt='Violin Plot'></div>";
echo "<div class='plot'><img src='data:image/png;base64," . base64_encode(file_get_contents($boxImage)) . "' alt='Box Plot'></div>";
echo "</div>";

// Clean up
unlink($dataFile);
unlink($violinImage);
unlink($boxImage);
unlink($rScript);

$conn->close();
?>
