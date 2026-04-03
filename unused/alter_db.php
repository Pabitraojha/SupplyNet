<?php
require_once 'config/db.php';

$queries = [
    "ALTER TABLE Users ADD COLUMN approval_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending'",
    "UPDATE Users SET approval_status = 'approved'", // Mark existing as approved so admin isn't locked out!
    "ALTER TABLE CustomersFeedback ADD COLUMN IsReplied BOOLEAN DEFAULT FALSE",
    "ALTER TABLE CustomersFeedback ADD COLUMN AdminReply TEXT NULL"
];

foreach ($queries as $query) {
    if ($conn->query($query) === TRUE) {
        echo "Successfully ran: $query\n";
    } else {
        echo "Error or already exists: " . $conn->error . "\n";
    }
}
$conn->close();
?>
