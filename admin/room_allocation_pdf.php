<?php
/**
 * admin/room_allocation_pdf.php — Room Allocation PDF Report
 * Hostel Management System
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

require_once __DIR__ . '/../lib/fpdf/fpdf.php';

class RoomPDF extends FPDF {
    function Header() {
        $this->SetFont('Arial', 'B', 16);
        $this->Cell(0, 10, 'Hostel Management System', 0, 1, 'C');
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 10, 'Room Allocation Report', 0, 1, 'C');
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 5, 'Date: ' . date('d M Y, h:i A'), 0, 1, 'C');
        $this->Ln(5);
        
        // Table Header
        $this->SetFont('Arial', 'B', 10);
        $this->SetFillColor(230, 230, 230);
        $this->Cell(20, 8, 'Room', 1, 0, 'C', true);
        $this->Cell(20, 8, 'Block', 1, 0, 'C', true);
        $this->Cell(25, 8, 'Type', 1, 0, 'C', true);
        $this->Cell(25, 8, 'Capacity', 1, 0, 'C', true);
        $this->Cell(100, 8, 'Assigned Students', 1, 1, 'L', true);
    }
    
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . ' / {nb}', 0, 0, 'C');
    }
}

// Fetch all rooms
$rooms = $conn->query("
    SELECT room_id, room_number, block, type, capacity, occupied, status
    FROM rooms
    ORDER BY block ASC, room_number ASC
");

$pdf = new RoomPDF();
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial', '', 9);

if ($rooms && $rooms->num_rows > 0) {
    while ($r = $rooms->fetch_assoc()) {
        $roomId = (int)$r['room_id'];
        
        // Fetch students in this room
        $stmt = $conn->prepare("SELECT name, student_code FROM students WHERE room_id = ? AND status = 'active'");
        $stmt->bind_param('i', $roomId);
        $stmt->execute();
        $stRes = $stmt->get_result();
        
        $students = [];
        while ($st = $stRes->fetch_assoc()) {
            $students[] = $st['name'] . ' (' . $st['student_code'] . ')';
        }
        $studentStr = empty($students) ? 'Vacant' : implode(', ', $students);
        
        // Calculate dynamic height based on student text length
        $textLines = ceil($pdf->GetStringWidth($studentStr) / 95);
        $lineHeight = max(8, 6 * $textLines);
        
        $x = $pdf->GetX();
        $y = $pdf->GetY();
        
        if ($y + $lineHeight > 270) {
            $pdf->AddPage();
            $y = $pdf->GetY();
        }
        
        $pdf->Cell(20, $lineHeight, $r['room_number'], 1, 0, 'C');
        $pdf->Cell(20, $lineHeight, $r['block'] ?: 'General', 1, 0, 'C');
        $pdf->Cell(25, $lineHeight, ucfirst($r['type']), 1, 0, 'C');
        $pdf->Cell(25, $lineHeight, $r['occupied'] . ' / ' . $r['capacity'], 1, 0, 'C');
        
        $pdf->SetXY($x + 90, $y);
        $pdf->MultiCell(100, ($textLines > 1 ? 6 : $lineHeight), $studentStr, 1, 'L');
        
        // Reset X,Y to next line
        $pdf->SetXY($x, $y + $lineHeight);
    }
} else {
    $pdf->Cell(190, 10, 'No rooms found.', 1, 1, 'C');
}

$pdf->Output('I', 'Room_Allocation_Report.pdf');
