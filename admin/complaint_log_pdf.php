<?php
/**
 * admin/complaint_log_pdf.php — Monthly Complaint Log PDF Report
 * Hostel Management System
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

require_once __DIR__ . '/../lib/fpdf/fpdf.php';

$month = $_GET['month'] ?? date('m');
$year  = $_GET['year']  ?? date('Y');

$monthName = date('F', mktime(0, 0, 0, $month, 10));

class ComplaintPDF extends FPDF {
    public $monthName;
    public $year;
    
    function Header() {
        $this->SetFont('Arial', 'B', 16);
        $this->Cell(0, 10, 'Hostel Management System', 0, 1, 'C');
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 10, 'Monthly Complaint Log: ' . $this->monthName . ' ' . $this->year, 0, 1, 'C');
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 5, 'Generated on: ' . date('d M Y, h:i A'), 0, 1, 'C');
        $this->Ln(5);
        
        // Table Header
        $this->SetFont('Arial', 'B', 9);
        $this->SetFillColor(230, 230, 230);
        $this->Cell(15, 8, 'ID', 1, 0, 'C', true);
        $this->Cell(30, 8, 'Date', 1, 0, 'C', true);
        $this->Cell(40, 8, 'Student', 1, 0, 'L', true);
        $this->Cell(30, 8, 'Category', 1, 0, 'C', true);
        $this->Cell(55, 8, 'Title', 1, 0, 'L', true);
        $this->Cell(20, 8, 'Status', 1, 1, 'C', true);
    }
    
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . ' / {nb}', 0, 0, 'C');
    }
}

// Fetch complaints for that month
$stmt = $conn->prepare("
    SELECT c.complaint_id, c.created_at, c.title, c.category, c.status, s.name as student_name
    FROM complaints c
    JOIN students s ON c.student_id = s.student_id
    WHERE MONTH(c.created_at) = ? AND YEAR(c.created_at) = ?
    ORDER BY c.created_at DESC
");
$stmt->bind_param('ss', $month, $year);
$stmt->execute();
$complaints = $stmt->get_result();

$pdf = new ComplaintPDF();
$pdf->monthName = $monthName;
$pdf->year = $year;
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial', '', 9);

if ($complaints && $complaints->num_rows > 0) {
    while ($c = $complaints->fetch_assoc()) {
        $title = mb_strimwidth($c['title'], 0, 30, '...');
        $student = mb_strimwidth($c['student_name'], 0, 20, '...');
        
        $pdf->Cell(15, 8, '#' . $c['complaint_id'], 1, 0, 'C');
        $pdf->Cell(30, 8, date('d M Y', strtotime($c['created_at'])), 1, 0, 'C');
        $pdf->Cell(40, 8, $student, 1, 0, 'L');
        $pdf->Cell(30, 8, ucfirst($c['category']), 1, 0, 'C');
        $pdf->Cell(55, 8, $title, 1, 0, 'L');
        $pdf->Cell(20, 8, ucfirst($c['status']), 1, 1, 'C');
    }
} else {
    $pdf->Cell(190, 10, 'No complaints found for this month.', 1, 1, 'C');
}

$pdf->Output('I', 'Complaint_Log_'.$monthName.'_'.$year.'.pdf');
