<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Http\Controllers;

use App\Factory\CloneInvoiceFactory;
use App\Factory\CloneInvoiceToQuoteFactory;
use App\Factory\CloneQuoteFactory;
use App\Factory\CloneQuoteToInvoiceFactory;
use App\Factory\QuoteFactory;
use App\Filters\QuoteFilters;
use App\Http\Requests\Quote\ActionQuoteRequest;
use App\Http\Requests\Quote\CreateQuoteRequest;
use App\Http\Requests\Quote\DestroyQuoteRequest;
use App\Http\Requests\Quote\EditQuoteRequest;
use App\Http\Requests\Quote\ShowQuoteRequest;
use App\Http\Requests\Quote\StoreQuoteRequest;
use App\Http\Requests\Quote\UpdateQuoteRequest;
use App\Jobs\Invoice\ZipInvoices;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Quote;
use App\Repositories\QuoteRepository;
use App\Transformers\InvoiceTransformer;
use App\Transformers\QuoteTransformer;
use App\Utils\TempFile;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\Request;

/**
 * Class QuoteController
 * @package App\Http\Controllers\QuoteController
 */

class QuoteController extends BaseController
{
    use MakesHash;

    protected $entity_type = Quote::class;

    protected $entity_transformer = QuoteTransformer::class;

    /**
     * @var QuoteRepository
     */
    protected $quote_repo;

    protected $base_repo;

    /**
     * QuoteController constructor.
     *
     * @param      \App\Repositories\QuoteRepository  $Quote_repo  The Quote repo
     */
    public function __construct(QuoteRepository $quote_repo)
    {
        parent::__construct();

        $this->quote_repo = $quote_repo;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     *
     *
     * @OA\Get(
     *      path="/api/v1/quotes",
     *      operationId="getQuotes",
     *      tags={"quotes"},
     *      summary="Gets a list of quotes",
     *      description="Lists quotes, search and filters allow fine grained lists to be generated.

        Query parameters can be added to performed more fine grained filtering of the quotes, these are handled by the QuoteFilters class which defines the methods available",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="A list of quotes",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Quote"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),

     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     *
     */
   
    public function index(QuoteFilters $filters)
    {
        $quotes = Quote::filter($filters);
      
        return $this->listResponse($quotes);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     *
     *
     *
     * @OA\Get(
     *      path="/api/v1/quotes/create",
     *      operationId="getQuotesCreate",
     *      tags={"quotes"},
     *      summary="Gets a new blank Quote object",
     *      description="Returns a blank object with default values",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="A blank Quote object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Quote"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     *
     */
    public function create(CreateQuoteRequest $request)
    {
        $quote = QuoteFactory::create(auth()->user()->company()->id, auth()->user()->id);

        return $this->itemResponse($quote);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param      \App\Http\Requests\Quote\StoreQuoteRequest  $request  The request
     *
     * @return \Illuminate\Http\Response
     *
     *
     *
     * @OA\Post(
     *      path="/api/v1/quotes",
     *      operationId="storeQuote",
     *      tags={"quotes"},
     *      summary="Adds a Quote",
     *      description="Adds an Quote to the system",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the saved Quote object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Quote"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     *
     */
    public function store(StoreQuoteRequest $request)
    {
        $client = Client::find($request->input('client_id'));

        $quote = $this->quote_repo->save($request->all(), QuoteFactory::create(auth()->user()->company()->id, auth()->user()->id));

        return $this->itemResponse($quote);
    }

    /**
     * Display the specified resource.
     *
     * @param      \App\Http\Requests\Quote\ShowQuoteRequest  $request  The request
     * @param      \App\Models\Quote                            $quote  The quote
     *
     * @return \Illuminate\Http\Response
     *
     *
     * @OA\Get(
     *      path="/api/v1/quotes/{id}",
     *      operationId="showQuote",
     *      tags={"quotes"},
     *      summary="Shows an Quote",
     *      description="Displays an Quote by id",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The Quote Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the Quote object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Quote"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     *
     */
    public function show(ShowQuoteRequest $request, Quote $quote)
    {
        return $this->itemResponse($quote);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param      \App\Http\Requests\Quote\EditQuoteRequest  $request  The request
     * @param      \App\Models\Quote                            $quote  The quote
     *
     * @return \Illuminate\Http\Response
     *
     *
     * @OA\Get(
     *      path="/api/v1/quotes/{id}/edit",
     *      operationId="editQuote",
     *      tags={"quotes"},
     *      summary="Shows an Quote for editting",
     *      description="Displays an Quote by id",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The Quote Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the Quote object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Quote"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     *
     */
    public function edit(EditQuoteRequest $request, Quote $quote)
    {
        return $this->itemResponse($quote);
    }
    
    /**
     * Update the specified resource in storage.
     *
     * @param      \App\Http\Requests\Quote\UpdateQuoteRequest  $request  The request
     * @param      \App\Models\Quote                              $quote  The quote
     *
     * @return \Illuminate\Http\Response
     *
     *
     * @OA\Put(
     *      path="/api/v1/quotes/{id}",
     *      operationId="updateQuote",
     *      tags={"quotes"},
     *      summary="Updates an Quote",
     *      description="Handles the updating of an Quote by id",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The Quote Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the Quote object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Quote"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     *
     */
    public function update(UpdateQuoteRequest $request, Quote $quote)
    {
        if ($request->entityIsDeleted($quote)) {
            return $request->disallowUpdate();
        }
        
        $quote = $this->quote_repo->save($request->all(), $quote);

        return $this->itemResponse($quote);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param      \App\Http\Requests\Quote\DestroyQuoteRequest  $request
     * @param      \App\Models\Quote                               $quote
     *
     * @return     \Illuminate\Http\Response
     *
     *
     * @OA\Delete(
     *      path="/api/v1/quotes/{id}",
     *      operationId="deleteQuote",
     *      tags={"quotes"},
     *      summary="Deletes a Quote",
     *      description="Handles the deletion of an Quote by id",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The Quote Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns a HTTP status",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     *
     */
    public function destroy(DestroyQuoteRequest $request, Quote $quote)
    {
        $quote->delete();

        return response()->json([], 200);
    }

    /**
     * Perform bulk actions on the list view
     *
     * @return Collection
     *
     *
     * @OA\Post(
     *      path="/api/v1/quotes/bulk",
     *      operationId="bulkQuotes",
     *      tags={"quotes"},
     *      summary="Performs bulk actions on an array of quotes",
     *      description="",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/index"),
     *      @OA\RequestBody(
     *         description="Hashed ids",
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 type="array",
     *                 @OA\Items(
     *                     type="integer",
     *                     description="Array of hashed IDs to be bulk 'actioned",
     *                     example="[0,1,2,3]",
     *                 ),
     *             )
     *         )
     *     ),
     *      @OA\Response(
     *          response=200,
     *          description="The Quote response",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Quote"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),

     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     *
     */
    public function bulk()
    {

        $action = request()->input('action');

        $ids = request()->input('ids');

        $quotes = Quote::withTrashed()->whereIn('id', $this->transformKeys($ids))->company()->get();

        if (!$quotes) {
            return response()->json(['message' => 'No Quote/s Found']);
        }

        /*
         * Download Invoice/s
         */

        if ($action == 'download' && $quotes->count() >= 1) {
            $quotes->each(function ($quote) {
                if (auth()->user()->cannot('view', $quote)) {
                    return response()->json(['message'=>'Insufficient privileges to access quote '. $quote->number]);
                }
            });

            ZipInvoices::dispatch($quotes, $quotes->first()->company, auth()->user()->email);

            return response()->json(['message' => 'Email Sent!'], 200);
        }

        if($action == 'convert') {

            $this->entity_type = Quote::class;
            $this->entity_transformer = QuoteTransformer::class;

            $quotes->each(function ($quote, $key) use ($action) {
                if (auth()->user()->can('edit', $quote) && $quote->service()->isConvertable()) {
                    $quote->service()->convertToInvoice();
                }
            });

            return $this->listResponse(Quote::withTrashed()->whereIn('id', $this->transformKeys($ids))->company());
        }

        /*
         * Send the other actions to the switch
         */
        $quotes->each(function ($quote, $key) use ($action) {
            if (auth()->user()->can('edit', $quote)) {
                $this->performAction($quote, $action, true);
            }
        });

        /* Need to understand which permission are required for the given bulk action ie. view / edit */

        return $this->listResponse(Quote::withTrashed()->whereIn('id', $this->transformKeys($ids))->company());
    }
    

    /**
     * Quote Actions
     *
     *
     *
     * @OA\Get(
     *      path="/api/v1/quotes/{id}/{action}",
     *      operationId="actionQuote",
     *      tags={"quotes"},
     *      summary="Performs a custom action on an Quote",
     *      description="Performs a custom action on an Quote.

        The current range of actions are as follows
        - clone_to_Quote
        - clone_to_quote
        - history
        - delivery_note
        - mark_paid
        - download
        - archive
        - delete
        - email",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The Quote Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Parameter(
     *          name="action",
     *          in="path",
     *          description="The action string to be performed",
     *          example="clone_to_quote",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the Quote object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Quote"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     *
     */
    
    public function action(ActionQuoteRequest $request, Quote $quote, $action)
    {
        return $this->performAction($quote, $action);
    }


    private function performAction(Quote $quote, $action, $bulk = false)
    {
        switch ($action) {
            case 'clone_to_invoice':

                $this->entity_type = Invoice::class;
                $this->entity_transformer = InvoiceTransformer::class;

                $invoice = CloneQuoteToInvoiceFactory::create($quote, auth()->user()->id);
                return $this->itemResponse($invoice);
                break;
            case 'clone_to_quote':
                $quote = CloneQuoteFactory::create($quote, auth()->user()->id);
                return $this->itemResponse($quote);
                break;
            case 'approve':
            //make sure it hasn't already been approved!!
                if ($quote->status_id != Quote::STATUS_SENT) {
                    return response()->json(['message' => 'Unable to approve this quote as it has expired.'], 400);
                }
                
                return $this->itemResponse($quote->service()->approve()->save());
                break;
            case 'history':
                # code...
                break;
            case 'download':
                    return response()->download(TempFile::path($quote->pdf_file_path()), basename($quote->pdf_file_path()));
                break;
            case 'archive':
                $this->invoice_repo->archive($quote);
                return $this->listResponse($quote);
                break;
            case 'delete':
                $this->quote_repo->delete($quote);
                return $this->listResponse($quote);
                break;
            case 'email':
                $this->quote->service()->sendEmail();
                return response()->json(['message'=>'email sent'], 200);
                break;
            case 'mark_sent':
                $quote->service()->markSent()->save();

                if (!$bulk) {
                    return $this->itemResponse($quote);
                }
                // no break
            default:
                return response()->json(['message' => "The requested action `{$action}` is not available."], 400);
                break;
        }
    }

    public function downloadPdf($invitation_key)
    {
        $invitation = $this->quote_repo->getInvitationByKey($invitation_key);
        $contact    = $invitation->contact;
        $quote    = $invitation->quote;

        $file_path = $quote->service()->getQuotePdf($contact);

        return response()->download($file_path);
    }
}
