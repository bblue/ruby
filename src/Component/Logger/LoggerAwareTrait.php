<?php
namespace bblue\ruby\Component\Logger;
use psr\Log\LoggerInterface;

trait LoggerAwareTrait
{
    /**
     * PSR3 compliant logger
     * @var LoggerInterface
     */
    protected $logger;
    
    /**
     * Method to assign a PSR3 logger to the class
     *
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger) {
        $this->logger = $logger;
    }
    
    public function hasLogger() {
        return isset($this->logger);
    }
}