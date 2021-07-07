<?php

namespace Drupal\application\Controller;

use DocuSign\eSign\Client\ApiException;
use DocuSign\eSign\Model\DateSigned;
use DocuSign\eSign\Model\EnvelopeDefinition;
use DocuSign\eSign\Model\Checkbox;
use DocuSign\eSign\Model\Document;
use DocuSign\eSign\Model\InitialHere;
use DocuSign\eSign\Model\Radio;
use DocuSign\eSign\Model\RadioGroup;
use DocuSign\eSign\Model\Recipients;
use DocuSign\eSign\Model\Signer;
use DocuSign\eSign\Model\SignHere;
use DocuSign\eSign\Model\Tabs;
use DocuSign\eSign\Model\Text;

use Drupal\application\Controller\ForgivenessForm;

class SForm {
    public const DOCS_PATH = __DIR__ . '/../../documents/';

    private $elements;

    /**
     * Creates envelope definition
     * Document 1: A Borrower Form PDF document.
     * DocuSign will convert all of the documents to the PDF format.
     * The recipients' field tags are placed using <b>anchor</b> strings.
     *
     * Parameters for the envelope: signer_email, signer_name, signer_client_id
     *
     * @param  $args array
     * @return EnvelopeDefinition -- returns an envelope definition
     */
    public function make_envelope(array $args, ForgivenessForm $form, &$elements): EnvelopeDefinition
    {
        $this->elements = $elements;
        #
        # The envelope has two recipients.
        # recipient 1 - signer
        # recipient 2 - cc
        # The envelope will be sent first to the signer.
        # After it is signed, a copy is sent to the cc person.
        #
        # create the envelope definition
        $envelope_definition = new EnvelopeDefinition([
            'email_subject' => 'Please sign this Forgivness Form'
        ]);
        # read files 2 and 3 from a local directory
        # The reads could raise an exception if the file is not available!
        $content_bytes = file_get_contents(self::DOCS_PATH . "PPP Forgiveness Application-3508.pdf");
        $forgivness_form_b64 = base64_encode($content_bytes);
         
        # Create the document models
        $document = new Document([  # create the DocuSign document object
            'document_base64' => $forgivness_form_b64,
            'name' => 'Loan Forgivness Application Form 3508',  # can be different from actual file name
            'file_extension' => 'pdf',  # many different document types are accepted
            'document_id' => '1'  # a label used to reference the doc
        ]);

        $borrower_name = new Text([
            'document_id' => "1", "page_number" => "1",
            "x_position" => "40", "y_position" => "80",
            "font" => "Arial", "font_size" => "size8",
            "value" => $this->elements["business_legal_name_borrower"]["#default_value"],
            "height" => "16", "width" => "160", "required" => "false"
        ]);

        
         
        $sign_here = new SignHere([
            'document_id' => "1", 'page_number' => "2",
            'x_position' => '40', 'y_position' => '664']);
 
        # Create the signer recipient model
        $signer = new Signer([
            'email' => $args['signer_email'], 'name' => $args['signer_name'],
            'role_name' => 'signer', 'recipient_id' => "1", 'routing_order' => "1"]);
        # routingOrder (lower means earlier) determines the order of deliveries
        # to the recipients. Parallel routing order is supported by using the
        # same integer as the order for two or more recipients.
         
        #$radio_groups = $this->getRadioGroup();
 
        #$checkbox_list = $this->getCheckboxList();
 
        $initial_here_list = $this->getInitialList();
        
        $text_list = [
            $borrower_name,
            
        ];

        $dateSigned = new DateSigned([
            'document_id' => "1", 'page_number' => "2",
            "x_position" => "360", "y_position" => "690",
            #"font" => "Arial", "font_size" => "size12",
            #"value" => date("m-j-Y"),
            "height" => "20", "width" => "140",
        ]);
        
        
         
        $signer->setTabs(new Tabs(['sign_here_tabs' => [$sign_here],
            'initial_here_tabs' => $initial_here_list,
            #'radio_group_tabs' => $radio_groups,
            #'checkbox_tabs' => $checkbox_list,
            'text_tabs' => $text_list,
            'date_signed_tabs' => [$dateSigned],
        ]));
 
         # Add the recipients to the envelope object
         $recipients = new Recipients([
             'signers' => [$signer]
         ]);
         $envelope_definition->setRecipients($recipients);
 
         # The order in the docs array determines the order in the envelope
         $envelope_definition->setDocuments([$document]);
 
        # Request that the envelope be sent by setting |status| to "sent".
        # To request that the envelope be created as a draft, set to "created"
        $envelope_definition->setStatus($args["status"]);
        return $envelope_definition;
    }
    
    private function getInitialList() {
        $initial_1 = new InitialHere([
            'document_id' => "1", "page_number" => "2",
            "x_position" => "25", "y_position" => "80",
            "height" => "10", "width" => "40", "required" => "false"
        ]);
        
        $initial_2 = new InitialHere([
            'document_id' => "1", "page_number" => "2",
            "x_position" => "25", "y_position" => "120",
            "height" => "10", "width" => "40", "required" => "false"
        ]);
        
        $initial_3 = new InitialHere([
            'document_id' => "1", "page_number" => "2",
            "x_position" => "25", "y_position" => "160",
            "height" => "10", "width" => "40", "required" => "false"
        ]);

        $initial_4 = new InitialHere([
            'document_id' => "1", "page_number" => "2",
            "x_position" => "25", "y_position" => "200",
            "height" => "10", "width" => "40", "required" => "false"
        ]);

        $initial_5 = new InitialHere([
            'document_id' => "1", "page_number" => "2",
            "x_position" => "25", "y_position" => "240",
            "height" => "10", "width" => "40", "required" => "false"
        ]);

        $initial_6 = new InitialHere([
            'document_id' => "1", "page_number" => "2",
            "x_position" => "25", "y_position" => "410",
            "height" => "10", "width" => "40", "required" => "false"
        ]);

        $initial_7 = new InitialHere([
            'document_id' => "1", "page_number" => "2",
            "x_position" => "25", "y_position" => "456",
            "height" => "10", "width" => "40", "required" => "false"
        ]);

        $initial_8 = new InitialHere([
            'document_id' => "1", "page_number" => "2",
            "x_position" => "25", "y_position" => "484",
            "height" => "10", "width" => "40", "required" => "false"
        ]);

        $initial_9 = new InitialHere([
            'document_id' => "1", "page_number" => "2",
            "x_position" => "25", "y_position" => "512",
            "height" => "10", "width" => "40", "required" => "false"
        ]);

        
        
        return [
            $initial_1, $initial_2, $initial_3, $initial_4,
            $initial_5, $initial_6, $initial_7, $initial_8,
            $initial_9, 
        ];
    }
}