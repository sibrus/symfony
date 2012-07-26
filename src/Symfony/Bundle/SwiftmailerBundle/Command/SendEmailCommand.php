<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SwiftmailerBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

use Symfony\Component\Console\Input\InputArgument;

/**
 * Send Emails from the spool.
 * Using custom transport.
 * Last modified: 7/20/12
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Clément JOBEILI <clement.jobeili@gmail.com>
 */
class SendEmailCommand extends ContainerAwareCommand
{
    /**
     * @see Command
     */
    protected function configure()
    {
        $this
            ->setName('swiftmailer:spool:send')
            ->setDescription('Sends emails from the spool')
            ->addArgument('storeId', InputArgument::REQUIRED, 'Store ID')
            ->addOption('message-limit', 0, InputOption::VALUE_OPTIONAL, 'The maximum number of messages to send.')
            ->addOption('time-limit', 0, InputOption::VALUE_OPTIONAL, 'The time limit for sending messages (in seconds).')
            ->setHelp(<<<EOF
The <info>swiftmailer:spool:send</info> command sends all emails from the spool.

<info>php app/console swiftmailer:spool:send --message-limit=10 --time-limit=10</info>

EOF
            )
        ;
    }

    /**
     * Last modified: 7/20/12
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $storeId = $input->getArgument('storeId');
        $doctrine = $this->getContainer()->get('doctrine');
        $em = $this->getContainer()->get('doctrine.orm.entity_manager');	
		
        if (!is_numeric($storeId))
		{
			$output->writeln("ERROR: Store ID must be an integer.");
			$output->writeln("Function now terminates...");
			exit();
		}
		else
		{	
			//Getting store
			$store = $doctrine->getRepository('ZenithSalonEntityBundle:Store')->findOneById($storeId);
			
			if ($store == null)
			{
				$output->writeln("ERROR: Store with given ID Does Not Exist.");
				$output->writeln("Function now terminates...");
				exit();
			}
		}
		
		//Getting Store setting
		$settingDQL = "SELECT s FROM ZenithSalonEntityBundle:Setting s " .
				" JOIN s.store store " .
				" WHERE store.id = :storeId AND s.isDeleted = FALSE " .
				" AND (s.settingName = 'PromSMTP' OR s.settingName = 'PromEmail' OR s.settingName = 'PromPassword' OR s.settingName = 'PromEmailAuthType')";
		
		$settings = $em->createQuery($settingDQL)->setParameter(//array(
				'storeId', $store->getId()
		)->getResult();
		
		$storeEmail = null;
		$storeEmailPass = null;
		$encrypt = null;
		$storeSMTP = null;
		
		foreach ($settings as $setting)
		{
			switch ($setting->getSettingName())
			{
				case 'PromSMTP':
					$storeSMTP = $setting->getStringValue();
					break;
				case 'PromEmail': //full email
					$storeEmail = $setting->getStringValue();
					break;
				case 'PromPassword':
					$storeEmailPass = $setting->getStringValue();
					break;
				case "PromEmailAuthType":
					$encrypt = strtolower($setting->getStringValue());
					break;
				default:
					break;
			}
		}
		
		if ($storeEmail == null || $storeSMTP == null || $storeEmailPass == null)
		{
			$output->writeln("ERROR: Mailing System Has NOT Been Set Up Yet.");
			$output->writeln("Function now terminates ...");
			exit();
		}
		
		 $spoolPath = "web/spool/" . $storeEmail; //This MUST be the same as the one specified in the MailSpooler class!
      
        //Creating transport
		if ($encrypt)
		{
			$transport = \Swift_SmtpTransport::newInstance($storeSMTP) 
							->setUsername($storeEmail)
							->setPassword($storeEmailPass)
							->setEncryption($encrypt);
		}
		else
		{
			$transport = \Swift_SmtpTransport::newInstance($storeSMTP)
							->setUsername($storeEmail)
							->setPassword($storeEmailPass);
		}
		
        $spool = new \Swift_FileSpool($spoolPath);        
        
        //Setting max # of msgs sent and time limit
        if ($spool instanceof \Swift_ConfigurableSpool) 
        {
            $spool->setMessageLimit($input->getOption('message-limit'));
            $spool->setTimeLimit($input->getOption('time-limit'));
        }
        
        $sent = $spool->flushQueue($transport);
        $output->writeln(sprintf('sent %s emails', $sent));
    }
}
