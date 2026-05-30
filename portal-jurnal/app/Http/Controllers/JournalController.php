<?php

namespace App\Http\Controllers;

use App\Models\Journal;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class JournalController extends Controller
{
    /**
     * Display a listing of the resource (public homepage - book style).
     */
    public function index(Request $request)
    {
        // Fast query with eager loading and pagination
        $query = Journal::approved()
            ->with(['category', 'user'])
            ->orderBy('created_at', 'desc');

        // Search filter
        if ($request->filled('search')) {
            $query->search($request->search);
        }

        // Category filter
        if ($request->filled('category')) {
            $query->where('category_id', $request->category);
        }

        // Paginate for performance (anti-bottleneck)
        $journals = $query->paginate(12)->withQueryString();
        
        // Get all categories for filter
        $categories = Category::ordered()->get();

        return view('welcome', compact('journals', 'categories'));
    }

    /**
     * Display the specified resource.
     */
    public function show($slug)
    {
        // Fast query by indexed slug
        $journal = Journal::approved()
            ->with(['category', 'user'])
            ->where('slug', $slug)
            ->firstOrFail();

        // Increment views (atomic operation to prevent race conditions)
        DB::table('journals')
            ->where('id', $journal->id)
            ->increment('views');

        return view('journals.show', compact('journal'));
    }

    /**
     * Download PDF
     */
    public function download(Journal $journal)
    {
        // Increment downloads
        DB::table('journals')
            ->where('id', $journal->id)
            ->increment('downloads');

        // Return R2 URL for redirect/download
        if ($journal->pdf_url) {
            return redirect($journal->pdf_url);
        }

        abort(404, 'PDF tidak tersedia');
    }
}
