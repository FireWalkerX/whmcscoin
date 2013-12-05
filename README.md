whmcscoin
======

WHMCS gateway for various altcoins.  

This is being developed by Robert Danielson from [Big Kesh](https://bigkesh.com). I'm not good at readmes. Sorry.  

This is a work in progress. If you find any issues or problems, please submit them. 
You'll need to set up the respective daemons on a server somewhere for this to work properly. 
This gateway accepts the coins into your own wallet, so you'll never have to worry about someone else holding them.  

I've used elements from [this Blockchain.info WHMCS gateway](https://bitbucket.org/Doctor_McKay/blockchain.info-whmcs-payment-gateway/) by [Doctor_McKay](https://bitbucket.org/Doctor_McKay) 
as well as [this Litecoin WHMCS gateway](https://github.com/dasher/WHMCS-Litecoin-Payment-Module) by [dasher](https://github.com/dasher).  

###To use:

You need to set up a cron job to access callback/litecoin.php which will check the addresses for payments. 
Other than that, you need to set up the *coin daemons. This script uses RPC to send commands to the daemon and retrieve the relevant information, 
so be sure to set your daemon to allow RPC from your WHMCS server. You'll need to enter the RPC credentials into 
the gateway settings after activating each gateway. Easy peasy.  

###To do:
- Find some way to avoid the cronjob requirement? Not sure if easily possible without changing daemon.
- Add refund option
- Add support for overpayment crediting
- Better error handling
- SSL support
- Payments per transaction instead of per address

### Donation Addresses
Bitcoin: 1GDofTWysZvq3HB1yputqFSZcZP8HzFLKF  
Litecoin: LR3ipCUqfPFkLiw27wr94Td5r6ZWygD8R9  
Feathercoin: 6gzcbWjgdJvgRm3kDgWjuQsyFhszR51MNW  
Namecoin: NFqWb2VSPrUCfP15saV6rCpqXkf4KZrczB  
Peercoin: PEfEFrfxswcEj3eShZkFH5xrL8VTYbVtWe  
Primecoin: Af6K4TGMdyJ4E7FCjCGyFDCV9j2k2v6J4r
