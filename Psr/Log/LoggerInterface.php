<?php

namespace Psr\Log;

/**
 * Minimal PSR-3 LoggerInterface stub for PHPMailer compatibility.
 *
 * This project does not use Composer, so the psr/log package is not
 * available.  PHPMailer uses LoggerInterface only to optionally accept
 * a PSR-3 logger for its Debugoutput property.  This stub provides the
 * interface so that the `instanceof` checks in PHPMailer & SMTP do not
 * error.  No actual logging implementation is needed unless you choose
 * to pass a logger to $mail->Debugoutput.
 *
 * @see https://www.php-fig.org/psr/psr-3/  PSR-3 Logger Interface
 */
interface LoggerInterface
{
    /**
     * System is unusable.
     */
    public function emergency($message, array $context = array());

    /**
     * Action must be taken immediately.
     */
    public function alert($message, array $context = array());

    /**
     * Critical conditions.
     */
    public function critical($message, array $context = array());

    /**
     * Runtime errors that do not require immediate action but should
     * typically be logged and monitored.
     */
    public function error($message, array $context = array());

    /**
     * Exceptional occurrences that are not errors.
     */
    public function warning($message, array $context = array());

    /**
     * Normal but significant events.
     */
    public function notice($message, array $context = array());

    /**
     * Interesting events.
     *
     * Example: User logs in, SQL logs.
     */
    public function info($message, array $context = array());

    /**
     * Detailed debug information.
     *
     * This is the only level PHPMailer uses internally.
     */
    public function debug($message, array $context = array());

    /**
     * Logs with an arbitrary level.
     */
    public function log($level, $message, array $context = array());
}

