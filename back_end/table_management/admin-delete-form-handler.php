<?php
session_start();

if (!isset($_SESSION['userId'])) {
    header("Location: /literature_hub/front_end/markup/index.php");
    die();
}

if ($_SESSION['userRole'] != "admin") {
    header("Location: /literature_hub/front_end/markup/index.php");
    die();
}

if ($_SERVER['REQUEST_METHOD'] == "POST") {
    $creationId = $_POST["creation-id"];

    try {
        require_once "../database-handler.inc.php";

        $pdo->beginTransaction();

        // Soft "delete" a record from the 'creations' table
        $sqlDelete = "UPDATE creations SET is_deleted = 1 WHERE creation_id = :id;";

        $stmt = $pdo->prepare($sqlDelete);

        $stmt->bindParam(":id", $creationId, PDO::PARAM_INT);

        $stmt->execute();

        // Insert a log entry into the 'changes_logs' table for the action
        $sqlInsertLog = "INSERT INTO changes_logs(log_timestamp, changed_by, action_type)
                         VALUES(NOW(), :user_id, 'delete');";
        $stmt2 = $pdo->prepare($sqlInsertLog);

        $stmt2->bindParam(":user_id", $_SESSION['userId'], PDO::PARAM_INT);

        $stmt2->execute();

        // Get the last inserted log_id for later use
        $logId = $pdo->lastInsertId();

        // Insert a relation entry into 'creations_changes_list' to link the log with the creation record
        $sqlInsertLogRelation = "INSERT INTO creations_changes_list(record_id, log_id)
                                 VALUES(:creation_id, :log_id);";
        $stmt3 = $pdo->prepare($sqlInsertLogRelation);

        $stmt3->bindParam(":creation_id", $creationId, PDO::PARAM_INT);
        $stmt3->bindParam(":log_id", $logId, PDO::PARAM_INT);

        $stmt3->execute();

        // Commit the transaction if everything executed correctly
        $pdo->commit();

        header("Location: /literature_hub/front_end/markup/index.php");

        die();

    } catch (PDOException $e) {
        // Rollback the transaction in case of an error
        $pdo->rollBack();

        header("Location: /literature_hub/front_end/markup/index.php?error");
        die("Query failed:" . $e->getMessage());
    }

} else {
    header("Location: /literature_hub/front_end/markup/index.php");
}