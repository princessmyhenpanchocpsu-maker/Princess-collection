import { ImageOff } from 'lucide-react';
import { Link } from 'react-router-dom';
import { ColorSwatch } from './ColorSelector';
import PriceDisplay from './PriceDisplay';

export default function ProductCard({ product }) {
    return (
        <Link to={`/product/${product.slug}`} className="group flex flex-col">
            <div className="relative aspect-[4/5] overflow-hidden rounded-2xl bg-white shadow-soft">
                {product.image ? (
                    <img
                        src={product.image.url}
                        alt={product.image.alt_text ?? product.name}
                        loading="lazy"
                        className="h-full w-full object-cover transition duration-500 group-hover:scale-105"
                    />
                ) : (
                    <div className="flex h-full w-full items-center justify-center bg-blush/40 text-brown/40">
                        <ImageOff className="h-8 w-8" strokeWidth={1.5} aria-hidden="true" />
                    </div>
                )}
                {!product.in_stock && (
                    <span className="absolute left-3 top-3 rounded-full bg-white/95 px-3 py-1 text-xs font-medium text-brown shadow-sm">Sold out</span>
                )}
            </div>
            <div className="mt-3 px-1">
                {product.category && <p className="text-xs uppercase tracking-wide text-brown/60">{product.category.name}</p>}
                <h3 className="mt-0.5 text-sm font-medium text-ink transition group-hover:text-rose-dark sm:text-base">{product.name}</h3>
                <div className="mt-1 flex items-center justify-between gap-2">
                    <PriceDisplay price={product.price} maxPrice={product.max_price} className="text-sm sm:text-base" />
                    {product.colors?.length > 0 && (
                        <span className="flex items-center gap-1" aria-label={`Colours: ${product.colors.join(', ')}`}>
                            {product.colors.slice(0, 4).map((color) => (
                                <ColorSwatch key={color} color={color} className="h-3.5 w-3.5" />
                            ))}
                        </span>
                    )}
                </div>
            </div>
        </Link>
    );
}
